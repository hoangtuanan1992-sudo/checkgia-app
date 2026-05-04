<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\ProductCodeExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardQuickScanController extends Controller
{
    public function index(Request $request): View
    {
        $websiteUrl = $this->normalizeWebsiteUrl($this->websiteInput($request));
        $websiteKey = $this->websiteKey($websiteUrl);
        $q = trim((string) $request->query('q', ''));
        $perPage = $this->perPage($request);
        $selectedJob = null;
        $latestRequest = null;
        $error = null;
        $products = new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => $request->url(), 'query' => $this->queryForLinks($websiteUrl, $q, $perPage)]
        );

        if (! Schema::hasTable('scanner_import_jobs') || ! Schema::hasTable('scanner_import_products')) {
            $error = 'Chưa có bảng dữ liệu quét. Hãy chạy migration import trên hosting.';
        } elseif ($websiteKey !== '') {
            $selectedJob = DB::table('scanner_import_jobs')
                ->orderByDesc('last_pushed_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->first(fn ($job): bool => $this->websiteKey((string) ($job->start_url ?? '')) === $websiteKey);

            if ($selectedJob) {
                $query = DB::table('scanner_import_products')
                    ->where('scanner_import_job_id', (int) $selectedJob->id);

                if ($q !== '') {
                    $query->where(function ($where) use ($q) {
                        $where->where('name', 'like', '%'.$q.'%')
                            ->orWhere('product_code', 'like', '%'.$q.'%')
                            ->orWhere('url', 'like', '%'.$q.'%');
                    });
                }

                $products = $query
                    ->orderBy('id')
                    ->paginate($perPage)
                    ->appends($this->queryForLinks($websiteUrl, $q, $perPage));

                $products->getCollection()->transform(fn ($product): array => $this->productRow($product));
            }
        }

        if (Schema::hasTable('scanner_scan_requests') && $websiteKey !== '') {
            $latestRequest = DB::table('scanner_scan_requests')
                ->where('url_key', $websiteKey)
                ->orderByDesc('updated_at')
                ->first();
        }

        return view('dashboard.quick-scan', [
            'websiteUrl' => $websiteUrl,
            'websiteKey' => $websiteKey,
            'q' => $q,
            'perPage' => $perPage,
            'error' => $error,
            'selectedJob' => $selectedJob,
            'latestRequest' => $latestRequest,
            'products' => $products,
        ]);
    }

    public function requestScan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_url' => ['required', 'string', 'max:2048'],
        ]);

        if (! Schema::hasTable('scanner_scan_requests')) {
            return back()->with('status', 'Chưa có bảng yêu cầu quét. Hãy chạy migration scanner_scan_requests trên hosting.');
        }

        $websiteUrl = $this->normalizeWebsiteUrl((string) $data['website_url']);
        $websiteKey = $this->websiteKey($websiteUrl);
        if ($websiteUrl === '' || $websiteKey === '') {
            return back()->withInput()->withErrors(['website_url' => 'Link website không hợp lệ.']);
        }

        $now = now();
        $payload = [
            'requested_by_user_id' => $request->user()->id,
            'requested_url' => $websiteUrl,
            'status' => 'pending',
            'external_job_id' => null,
            'requested_at' => $now,
            'claimed_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
            'updated_at' => $now,
        ];

        $exists = DB::table('scanner_scan_requests')
            ->where('url_key', $websiteKey)
            ->exists();

        if ($exists) {
            DB::table('scanner_scan_requests')
                ->where('url_key', $websiteKey)
                ->update($payload);
        } else {
            DB::table('scanner_scan_requests')->insert($payload + [
                'url_key' => $websiteKey,
                'created_at' => $now,
            ]);
        }

        return redirect()
            ->route('dashboard.quick-scan', ['website_url' => $websiteUrl])
            ->with('status', 'Đã gửi lệnh quét. Vui lòng chờ khoảng 1 ngày để phần mềm Windows quét xong.');
    }

    public function addToCompare(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_url' => ['nullable', 'string', 'max:2048'],
            'scanner_product_ids_json' => ['required', 'string'],
        ]);

        $ids = json_decode((string) $data['scanner_product_ids_json'], true);
        if (! is_array($ids)) {
            return back()->with('status', 'Danh sách sản phẩm đã chọn không hợp lệ.');
        }

        $ids = collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('status', 'Bạn chưa chọn sản phẩm nào.');
        }

        $userId = $request->user()->effectiveUserId();
        $scannerProducts = DB::table('scanner_import_products')
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->get();

        $added = 0;
        foreach ($scannerProducts as $scannerProduct) {
            $url = trim((string) (($scannerProduct->url ?? '') ?: ($scannerProduct->link ?? '')));
            $name = trim((string) ($scannerProduct->name ?? ''));
            $price = (int) ($scannerProduct->price_value ?? 0);
            if ($url === '' || $name === '' || $price <= 0) {
                continue;
            }

            $product = Product::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'product_url' => $url,
                ],
                [
                    'name' => $name,
                    'price' => $price,
                ]
            );

            if (! $product->wasRecentlyCreated) {
                $product->update([
                    'name' => $name,
                    'price' => $price,
                ]);
            }

            ProductPriceHistory::query()->create([
                'product_id' => $product->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);

            $added++;
        }

        return redirect(route('dashboard').'#comparisonCard')
            ->with('status', 'Đã thêm '.$added.' sản phẩm vào bảng so sánh.');
    }

    /**
     * @return array{id: int, code: string, name: string, priceValue: int, priceText: string, url: string, sourceUrl: string, updatedAt: string}
     */
    private function productRow(object $product): array
    {
        $name = (string) ($product->name ?? '');
        $url = (string) (($product->url ?? '') ?: ($product->link ?? ''));
        $sourceUrl = (string) ($product->source_url ?? '');

        return [
            'id' => (int) ($product->id ?? 0),
            'code' => ProductCodeExtractor::best((string) ($product->product_code ?? ''), $name, $url, $sourceUrl),
            'name' => $name,
            'priceValue' => (int) ($product->price_value ?? 0),
            'priceText' => (string) ($product->price_text ?? ''),
            'url' => $url,
            'sourceUrl' => $sourceUrl,
            'updatedAt' => (string) ($product->updated_at ?? ''),
        ];
    }

    private function websiteInput(Request $request): string
    {
        foreach (['website_url', 'url', 'link'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeWebsiteUrl(string $url): string
    {
        $url = trim(rawurldecode($url));
        if ($url === '') {
            return '';
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.ltrim($url, '/');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $path = trim((string) ($parts['path'] ?? ''), '/');

        return $scheme.'://'.$host.$port.($path !== '' ? '/'.$path : '');
    }

    private function websiteKey(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.ltrim($url, '/');
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return '';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = trim((string) ($parts['path'] ?? ''), '/');

        return $host.($path !== '' ? '/'.$path : '');
    }

    private function perPage(Request $request): int
    {
        $raw = trim((string) $request->query('per_page', '200'));
        $value = ctype_digit($raw) ? (int) $raw : 200;

        return in_array($value, [50, 100, 200, 500], true) ? $value : 200;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryForLinks(string $websiteUrl, string $q, int $perPage): array
    {
        $query = [];
        if ($websiteUrl !== '') {
            $query['website_url'] = $websiteUrl;
        }
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($perPage !== 200) {
            $query['per_page'] = $perPage;
        }

        return $query;
    }
}
