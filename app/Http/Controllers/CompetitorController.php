<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Services\ConfiguredProductScraper;
use App\Services\PriceScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CompetitorController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $site = CompetitorSite::firstOrCreate(
            ['user_id' => $request->user()->effectiveUserId(), 'name' => $data['name']],
            ['name' => $data['name']]
        );

        $product->competitors()->create([
            'competitor_site_id' => $site->id,
            'name' => $data['name'],
            'url' => $data['url'],
        ]);

        return back()->with('status', 'Đã thêm đối thủ');
    }

    public function update(Request $request, Product $product, Competitor $competitor): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId() && $competitor->product_id === $product->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $site = CompetitorSite::firstOrCreate(
            ['user_id' => $request->user()->effectiveUserId(), 'name' => $data['name']],
            ['name' => $data['name']]
        );

        $updates = [
            'competitor_site_id' => $site->id,
            'name' => $data['name'],
            'url' => $data['url'],
        ];
        if (trim((string) $competitor->url) !== trim((string) $data['url'])) {
            if (Schema::hasColumn('competitors', 'variant_key')) {
                $updates['variant_key'] = null;
            }
            if (Schema::hasColumn('competitors', 'variant_name')) {
                $updates['variant_name'] = null;
            }
        }

        $competitor->update($updates);

        return back()->with('status', 'Đã cập nhật đối thủ');
    }

    public function destroy(Request $request, Product $product, Competitor $competitor): RedirectResponse
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId() && $competitor->product_id === $product->id, 404);

        $competitor->delete();

        return back()->with('status', 'Đã xoá đối thủ');
    }

    public function updateUrl(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $url = trim((string) ($data['url'] ?? ''));
        if ($clear || $url === '') {
            $competitor->delete();

            return back()->with('status', 'Đã xoá URL');
        }

        $updates = ['url' => $url];
        if (trim((string) $competitor->url) !== $url) {
            if (Schema::hasColumn('competitors', 'variant_key')) {
                $updates['variant_key'] = null;
            }
            if (Schema::hasColumn('competitors', 'variant_name')) {
                $updates['variant_name'] = null;
            }
        }
        $competitor->update($updates);

        $this->scrapeAndStoreCompetitorPrice($competitor, $competitor->competitorSite);

        return back()->with('status', 'Đã cập nhật URL');
    }

    public function upsertUrl(Request $request, Product $product, CompetitorSite $competitorSite): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        abort_unless($user, 403);
        if (! $user->isAdmin() && ((int) $product->user_id !== (int) $user->effectiveUserId() || (int) $competitorSite->user_id !== (int) $user->effectiveUserId())) {
            abort(404);
        }

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $url = trim((string) ($data['url'] ?? ''));

        if ($clear || $url === '') {
            $existing = $product->competitors()->where('competitor_site_id', $competitorSite->id)->first();
            if ($existing) {
                if (trim((string) ($existing->note ?? '')) !== '') {
                    $existing->url = '';
                    if (Schema::hasColumn('competitors', 'variant_key')) {
                        $existing->variant_key = null;
                    }
                    if (Schema::hasColumn('competitors', 'variant_name')) {
                        $existing->variant_name = null;
                    }
                    $existing->markPriceMissing();
                    $existing->save();
                } else {
                    $existing->delete();
                }
            }

            return back()->with('status', 'Đã xoá URL');
        }

        $competitor = $product->competitors()->firstOrNew([
            'competitor_site_id' => $competitorSite->id,
        ]);
        $competitor->name = $competitorSite->name;
        if (! $competitor->exists || trim((string) $competitor->url) !== $url) {
            if (Schema::hasColumn('competitors', 'variant_key')) {
                $competitor->variant_key = null;
            }
            if (Schema::hasColumn('competitors', 'variant_name')) {
                $competitor->variant_name = null;
            }
        }
        $competitor->url = $url;
        $competitor->save();

        $this->scrapeAndStoreCompetitorPrice($competitor, $competitorSite);

        return back()->with('status', 'Đã cập nhật URL');
    }

    public function updateNote(Request $request, Product $product, CompetitorSite $competitorSite): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        abort_unless($user, 403);
        if (! $user->isAdmin() && ((int) $product->user_id !== (int) $user->effectiveUserId() || (int) $competitorSite->user_id !== (int) $user->effectiveUserId())) {
            abort(404);
        }

        if (! Schema::hasColumn('competitors', 'note')) {
            return back()
                ->withInput()
                ->withErrors(['note' => 'Database chưa có cột note. Hãy chạy migration trên hosting: php artisan migrate --force']);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $note = trim((string) ($data['note'] ?? ''));
        $competitor = $product->competitors()->firstOrNew([
            'competitor_site_id' => $competitorSite->id,
        ]);

        if ($note === '' && ! $competitor->exists) {
            return back()->with('status', 'Chưa có note để lưu.');
        }

        if ($note === '' && trim((string) ($competitor->url ?? '')) === '') {
            $competitor->delete();

            return back()->with('status', 'Đã xoá note.');
        }

        $competitor->name = $competitorSite->name;
        $competitor->url = trim((string) ($competitor->url ?? ''));
        $competitor->note = $note !== '' ? $note : null;
        $competitor->save();

        return back()->with('status', 'Đã lưu note.');
    }

    public function storePrice(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
        ]);

        CompetitorPrice::create([
            'competitor_id' => $competitor->id,
            'price' => $data['price'],
            'fetched_at' => now(),
        ]);
        $competitor->markPriceAvailable();

        return back()->with('status', 'Đã thêm giá đối thủ');
    }

    public function updatePriceAdjustment(Request $request, Competitor $competitor)
    {
        $product = $competitor->product;
        $user = $request->user();

        if (! $product || ! $user || ($user->role !== 'admin' && (int) $product->user_id !== (int) $user->effectiveUserId())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Không có quyền thao tác.'], 403);
            }
            abort(403);
        }

        $data = $request->validate([
            'price_adjustment' => ['nullable', 'string', 'max:64'],
            'variant_key' => ['nullable', 'string', 'max:255'],
        ]);

        $input = trim((string) ($data['price_adjustment'] ?? ''));
        if ($input === '') {
            $variantData = $this->resolveVariantUpdate($request, $competitor);
            if ($variantData instanceof JsonResponse || $variantData instanceof RedirectResponse) {
                return $variantData;
            }

            $competitor->forceFill(array_merge(['price_adjustment' => 0], $variantData['attributes']))->save();
            $this->storeVariantPriceIfNeeded($competitor, $variantData);

            if ($request->expectsJson()) {
                return response()->json(array_merge([
                    'ok' => true,
                    'price_adjustment' => 0,
                    'reload' => $variantData['touched'],
                ], $variantData['response']));
            }

            return back()->with('status', 'Đã lưu điều chỉnh giá');
        }

        $sign = 1;
        if (str_starts_with($input, '-')) {
            $sign = -1;
            $input = trim(substr($input, 1));
        } elseif (str_starts_with($input, '+')) {
            $input = trim(substr($input, 1));
        }

        $digits = preg_replace('/[^0-9]/', '', $input) ?? '';
        if ($digits === '') {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['price_adjustment' => ['Giá điều chỉnh không hợp lệ.']]], 422);
            }

            return back()->withErrors(['price_adjustment' => 'Giá điều chỉnh không hợp lệ.']);
        }

        $adjustment = $sign * (int) $digits;
        $variantData = $this->resolveVariantUpdate($request, $competitor);
        if ($variantData instanceof JsonResponse || $variantData instanceof RedirectResponse) {
            return $variantData;
        }

        $competitor->forceFill(array_merge(['price_adjustment' => $adjustment], $variantData['attributes']))->save();
        $this->storeVariantPriceIfNeeded($competitor, $variantData);

        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'ok' => true,
                'price_adjustment' => $adjustment,
                'reload' => $variantData['touched'],
            ], $variantData['response']));
        }

        return back()->with('status', 'Đã lưu điều chỉnh giá');
    }

    public function scrapeLatestPrice(Request $request, Competitor $competitor): RedirectResponse
    {
        $product = $competitor->product;
        abort_unless($product && $product->user_id === $request->user()->effectiveUserId(), 404);

        $price = $this->scrapeAndStoreCompetitorPrice($competitor, $competitor->competitorSite);
        if (! is_null($price)) {
            return back()->with('status', 'Đã cập nhật giá: '.number_format($price, 0, ',', '.').' đ');
        }

        return back()->withErrors(['price' => 'Không lấy được giá. Hãy kiểm tra Thư viện XPath theo domain hoặc XPath riêng của shop.']);
    }

    public function variants(Request $request, Competitor $competitor): JsonResponse
    {
        $product = $competitor->product;
        $user = $request->user();

        if (! $product || ! $user || ($user->role !== 'admin' && (int) $product->user_id !== (int) $user->effectiveUserId())) {
            return response()->json(['message' => 'Không có quyền thao tác.'], 403);
        }

        if (! $competitor->url) {
            return response()->json([
                'ok' => true,
                'variants' => [],
                'selected_key' => null,
            ]);
        }

        try {
            $variants = (new ConfiguredProductScraper(new PriceScraper))->variantsForUrl($competitor->url);
        } catch (\Throwable) {
            $variants = [];
        }

        return response()->json([
            'ok' => true,
            'variants' => array_map(fn ($variant) => [
                'key' => (string) $variant['key'],
                'name' => (string) $variant['name'],
                'price' => (int) $variant['price'],
                'price_text' => number_format((int) $variant['price'], 0, ',', '.').'đ',
            ], $variants),
            'selected_key' => $competitor->variant_key ?? null,
        ]);
    }

    private function resolveVariantUpdate(Request $request, Competitor $competitor): array|JsonResponse|RedirectResponse
    {
        if (! $request->has('variant_key')) {
            return [
                'touched' => false,
                'attributes' => [],
                'response' => [],
                'price' => null,
            ];
        }

        if (! Schema::hasColumn('competitors', 'variant_key') || ! Schema::hasColumn('competitors', 'variant_name')) {
            $message = 'Database chưa có cột cấu hình biến thể. Hãy chạy migration trên hosting: php artisan migrate --force';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['variant_key' => $message]);
        }

        $variantKey = trim((string) $request->input('variant_key', ''));
        if ($variantKey === '') {
            return [
                'touched' => true,
                'attributes' => [
                    'variant_key' => null,
                    'variant_name' => null,
                ],
                'response' => [
                    'variant_key' => null,
                    'variant_name' => null,
                    'variant_price' => null,
                ],
                'price' => null,
            ];
        }

        if (! $competitor->url) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Ô đối thủ chưa có URL để chọn cấu hình.'], 422)
                : back()->withErrors(['variant_key' => 'Ô đối thủ chưa có URL để chọn cấu hình.']);
        }

        try {
            $variants = (new ConfiguredProductScraper(new PriceScraper))->variantsForUrl($competitor->url);
        } catch (\Throwable) {
            $variants = [];
        }

        $variant = collect($variants)->first(fn ($item) => (string) $item['key'] === $variantKey);
        if (! $variant) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Không tìm thấy cấu hình này trên website đối thủ.'], 422)
                : back()->withErrors(['variant_key' => 'Không tìm thấy cấu hình này trên website đối thủ.']);
        }

        return [
            'touched' => true,
            'attributes' => [
                'variant_key' => (string) $variant['key'],
                'variant_name' => (string) $variant['name'],
            ],
            'response' => [
                'variant_key' => (string) $variant['key'],
                'variant_name' => (string) $variant['name'],
                'variant_price' => (int) $variant['price'],
            ],
            'price' => (int) $variant['price'],
        ];
    }

    private function storeVariantPriceIfNeeded(Competitor $competitor, array $variantData): void
    {
        $price = $variantData['price'] ?? null;
        if (is_null($price)) {
            return;
        }

        $price = (int) $price;
        if ($price <= 0) {
            $competitor->markPriceMissing();

            return;
        }

        $competitor->markPriceAvailable();
        $latest = $competitor->prices()->latest('fetched_at')->first();
        if (! $latest || (int) $latest->price !== $price) {
            CompetitorPrice::create([
                'competitor_id' => $competitor->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);
        }
    }

    private function scrapeAndStoreCompetitorPrice(Competitor $competitor, ?CompetitorSite $site): ?int
    {
        if (! $competitor->url) {
            $competitor->markPriceMissing();

            return null;
        }

        try {
            $priceResult = (new ConfiguredProductScraper(new PriceScraper))
                ->scrapeCompetitorPrice($competitor->url, $site, $competitor->variant_key ?? null);
        } catch (\Throwable $e) {
            $competitor->markPriceMissing();

            return null;
        }

        $price = $priceResult['price'] ?? null;
        if (is_null($price)) {
            $competitor->markPriceMissing();

            return null;
        }

        $price = (int) $price;
        $competitor->markPriceAvailable();
        $latest = $competitor->prices()->latest('fetched_at')->first();
        if (! $latest || (int) $latest->price !== $price) {
            CompetitorPrice::create([
                'competitor_id' => $competitor->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);
        }

        return $price;
    }
}
