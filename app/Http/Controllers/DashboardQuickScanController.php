<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGroup;
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
        $userId = $request->user()->effectiveUserId();
        $q = trim((string) $request->query('q', ''));
        $productFilter = $this->productFilter($request);
        $perPage = $this->perPage($request);
        $selectedJob = null;
        $latestRequest = null;
        $error = null;
        $allProductIds = collect();
        $products = new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => $request->url(), 'query' => $this->queryForLinks($websiteUrl, $q, $productFilter, $perPage)]
        );

        if (! Schema::hasTable('scanner_import_jobs') || ! Schema::hasTable('scanner_import_products')) {
            $error = 'Chưa có bảng dữ liệu quét. Hãy chạy migration import trên hosting.';
        } elseif ($websiteKey !== '') {
            $matchingJobs = DB::table('scanner_import_jobs')
                ->orderByDesc('last_pushed_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->filter(fn ($job): bool => $this->websiteKey((string) ($job->start_url ?? '')) === $websiteKey)
                ->values();

            $selectedJob = $matchingJobs->first();

            if ($selectedJob) {
                $previousJobIds = $matchingJobs
                    ->skip(1)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                $query = DB::table('scanner_import_products')
                    ->where('scanner_import_job_id', (int) $selectedJob->id);

                if ($q !== '') {
                    $query->where(function ($where) use ($q) {
                        $where->where('name', 'like', '%'.$q.'%')
                            ->orWhere('product_code', 'like', '%'.$q.'%')
                            ->orWhere('url', 'like', '%'.$q.'%');
                    });
                }
                $this->applyProductFilter($query, $productFilter, $previousJobIds);

                $allProductIds = (clone $query)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                $products = $query
                    ->orderBy('id')
                    ->paginate($perPage)
                    ->appends($this->queryForLinks($websiteUrl, $q, $productFilter, $perPage));

                $pageProducts = $products->getCollection();
                $scannerUrls = $pageProducts
                    ->map(fn ($product): string => trim((string) (($product->url ?? '') ?: ($product->link ?? ''))))
                    ->filter()
                    ->unique()
                    ->values();
                $compareUrlMap = $scannerUrls->isNotEmpty()
                    ? Product::query()
                        ->where('user_id', $userId)
                        ->whereIn('product_url', $scannerUrls->all())
                        ->pluck('product_url')
                        ->mapWithKeys(fn ($url): array => [trim((string) $url) => true])
                    : collect();

                $products->setCollection($pageProducts->map(function ($product) use ($compareUrlMap): array {
                    $url = trim((string) (($product->url ?? '') ?: ($product->link ?? '')));

                    return $this->productRow($product, $compareUrlMap->has($url));
                }));
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
            'productGroups' => ProductGroup::query()
                ->where('user_id', $userId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'q' => $q,
            'productFilter' => $productFilter,
            'perPage' => $perPage,
            'error' => $error,
            'selectedJob' => $selectedJob,
            'latestRequest' => $latestRequest,
            'products' => $products,
            'allProductIds' => $allProductIds,
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
            'q' => ['nullable', 'string', 'max:255'],
            'product_filter' => ['nullable', 'in:all,newest,priced,unpriced'],
            'per_page' => ['nullable', 'integer', 'in:50,100,200,500'],
            'page' => ['nullable', 'integer', 'min:1'],
            'product_group_id' => ['nullable', 'integer'],
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
        $productGroupId = null;
        if (! empty($data['product_group_id'])) {
            $productGroupId = ProductGroup::query()
                ->where('user_id', $userId)
                ->whereKey((int) $data['product_group_id'])
                ->value('id');

            if (! $productGroupId) {
                return back()->with('status', 'Nhóm sản phẩm không hợp lệ hoặc không thuộc tài khoản này.');
            }
        }

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
                    'product_group_id' => $productGroupId,
                    'name' => $name,
                    'price' => $price,
                ]
            );

            if (! $product->wasRecentlyCreated) {
                $product->update([
                    'product_group_id' => $productGroupId,
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

        $redirectQuery = $this->queryForLinks(
            $this->normalizeWebsiteUrl((string) ($data['website_url'] ?? '')),
            trim((string) ($data['q'] ?? '')),
            (string) ($data['product_filter'] ?? 'all'),
            (int) ($data['per_page'] ?? 200)
        );
        if (! empty($data['page']) && (int) $data['page'] > 1) {
            $redirectQuery['page'] = (int) $data['page'];
        }

        return redirect()
            ->route('dashboard.quick-scan', $redirectQuery)
            ->with('status', 'Đã thêm '.$added.' sản phẩm vào bảng so sánh.');
    }

    /**
     * @return array{id: int, code: string, name: string, priceValue: int, priceText: string, url: string, sourceUrl: string, updatedAt: string, inCompare: bool}
     */
    private function productRow(object $product, bool $inCompare = false): array
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
            'inCompare' => $inCompare,
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

    private function productFilter(Request $request): string
    {
        $filter = (string) $request->query('product_filter', 'all');

        return in_array($filter, ['all', 'newest', 'priced', 'unpriced'], true) ? $filter : 'all';
    }

    /**
     * @param array<int, int> $previousJobIds
     */
    private function applyProductFilter($query, string $filter, array $previousJobIds): void
    {
        if ($filter === 'priced') {
            $query->where('price_value', '>', 0);

            return;
        }

        if ($filter === 'unpriced') {
            $query->where(function ($where) {
                $where->whereNull('price_value')
                    ->orWhere('price_value', '<=', 0);
            });

            return;
        }

        if ($filter !== 'newest') {
            return;
        }

        $since = now()->subMonth();
        $query->where(function ($where) use ($since) {
            $where->where('imported_at', '>=', $since)
                ->orWhere('created_at', '>=', $since)
                ->orWhere('updated_at', '>=', $since);
        });

        if ($previousJobIds === []) {
            return;
        }

        $query->whereNotExists(function ($sub) use ($previousJobIds) {
            $sub->select(DB::raw(1))
                ->from('scanner_import_products as previous_products')
                ->whereIn('previous_products.scanner_import_job_id', $previousJobIds)
                ->where(function ($same) {
                    $same->whereColumn('previous_products.url_hash', 'scanner_import_products.url_hash')
                        ->orWhereColumn('previous_products.dedupe_hash', 'scanner_import_products.dedupe_hash');
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function queryForLinks(string $websiteUrl, string $q, string $productFilter, int $perPage): array
    {
        $query = [];
        if ($websiteUrl !== '') {
            $query['website_url'] = $websiteUrl;
        }
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($productFilter !== 'all') {
            $query['product_filter'] = $productFilter;
        }
        if ($perPage !== 200) {
            $query['per_page'] = $perPage;
        }

        return $query;
    }
}
