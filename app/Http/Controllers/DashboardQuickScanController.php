<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeProductPrices;
use App\Jobs\QuickScanScrapeRun;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ShopeeProduct;
use App\Models\UserScrapeSetting;
use App\Models\User;
use App\Services\PriceScraper;
use App\Services\ProductCodeExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardQuickScanController extends Controller
{
    private function shouldSkipNonProductUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? trim($path) : '';
        $path = $path !== '' ? $path : '/';

        if ($path === '/' || $path === '') {
            return true;
        }

        if (str_ends_with($path, '/')) {
            return true;
        }

        $lower = strtolower($path);
        foreach (['/c/', '/category/', '/danh-muc/', '/collections/', '/collection/', '/search', '/tim-kiem'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function clearRuns(Request $request): RedirectResponse
    {
        if ($request->user()->isViewer()) {
            abort(403);
        }

        $userId = $request->user()->effectiveUserId();
        DB::table('quick_scan_runs')->where('user_id', $userId)->delete();

        return redirect()->route('dashboard.quick-scan')->with('status', 'Đã xoá tất cả run.');
    }

    public function index(Request $request): View
    {
        $authUser = $request->user();

        $perPage = 50;
        if ($authUser && Schema::hasColumn('users', 'quick_scan_per_page')) {
            $stored = User::query()->whereKey($authUser->id)->value('quick_scan_per_page');
            $storedInt = is_null($stored) ? null : (int) $stored;
            if (in_array($storedInt, [25, 50, 100, 200], true)) {
                $perPage = $storedInt;
            }
        }

        $perPageRaw = trim((string) $request->query('per_page', ''));
        if ($perPageRaw !== '' && ctype_digit($perPageRaw)) {
            $pp = (int) $perPageRaw;
            if (in_array($pp, [25, 50, 100, 200], true)) {
                $perPage = $pp;
                if ($authUser && Schema::hasColumn('users', 'quick_scan_per_page')) {
                    User::query()->whereKey($authUser->id)->update(['quick_scan_per_page' => $pp]);
                }
            }
        }

        $q = trim((string) $request->query('q', ''));
        $websiteUrl = trim((string) $request->query('website_url', ''));
        $websiteUrl = $websiteUrl !== '' ? $this->normalizeWebsiteUrl($websiteUrl) : '';
        $websiteKey = $this->websiteKey($websiteUrl);
        $scannerMode = (string) $request->query('mode', '') === 'priced' ? 'priced' : 'all';
        $scannerError = null;
        $selectedScannerJob = null;
        $scanRequest = null;
        $scannerJobs = collect();
        $scannerProductIds = [];
        $scannerProducts = new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        if (! Schema::hasTable('scanner_import_jobs') || ! Schema::hasTable('scanner_import_products')) {
            $scannerError = 'Chua co bang luu du lieu import. Hay chay migration tren hosting.';

            return view('dashboard.quick-scan', [
                'importEndpoint' => url('/api/products/import'),
                'apiKeyConfigured' => trim((string) config('services.checkgia_import.api_key', '')) !== '',
                'scannerError' => $scannerError,
                'scannerJobs' => $scannerJobs,
                'selectedScannerJob' => $selectedScannerJob,
                'selectedJobId' => '',
                'websiteUrl' => $websiteUrl,
                'scanRequest' => $scanRequest,
                'scannerProductIds' => $scannerProductIds,
                'scannerProducts' => $scannerProducts,
                'scannerMode' => $scannerMode,
                'q' => $q,
                'perPage' => $perPage,
            ]);
        }

        $scannerJobs = DB::table('scanner_import_jobs')
            ->orderByDesc('last_pushed_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($job) => [
                'id' => (string) ($job->external_job_id ?? ''),
                'dbId' => (int) ($job->id ?? 0),
                'startUrl' => (string) ($job->start_url ?? ''),
                'websiteKey' => $this->websiteKey((string) ($job->start_url ?? '')),
                'status' => 'imported',
                'mode' => (string) ($job->mode ?? ''),
                'productCount' => (int) ($job->imported_product_count ?? 0),
                'filteredProductCount' => (int) ($job->priced_product_count ?? 0),
                'visitedCount' => 0,
                'maxPages' => 0,
                'batchIndex' => (int) ($job->batch_index ?? 0),
                'batchTotal' => (int) ($job->batch_total ?? 0),
                'createdAt' => (string) ($job->created_at ?? ''),
                'updatedAt' => (string) (($job->last_pushed_at ?? null) ?: ($job->updated_at ?? '')),
            ])
            ->values();

        $selectedJobId = '';
        if ($websiteUrl !== '' && $websiteKey === '') {
            $scannerError = 'Link website khong hop le.';
        }

        if ($websiteKey !== '') {
            $selectedScannerJob = $scannerJobs->first(fn ($job) => (string) ($job['websiteKey'] ?? '') === $websiteKey);
            $selectedScannerJob = is_array($selectedScannerJob) ? $selectedScannerJob : null;
            $selectedJobId = (string) ($selectedScannerJob['id'] ?? '');

            if (! $selectedScannerJob && Schema::hasTable('scanner_scan_requests')) {
                $row = DB::table('scanner_scan_requests')
                    ->where('url_key', $websiteKey)
                    ->first();
                if ($row) {
                    $scanRequest = [
                        'id' => (int) ($row->id ?? 0),
                        'status' => (string) ($row->status ?? ''),
                        'requestedUrl' => (string) ($row->requested_url ?? ''),
                        'requestedAt' => (string) ($row->requested_at ?? ''),
                        'claimedAt' => (string) ($row->claimed_at ?? ''),
                        'completedAt' => (string) ($row->completed_at ?? ''),
                        'error' => (string) ($row->error ?? ''),
                    ];
                }
            }
        }

        $selectedJobDbId = (int) ($selectedScannerJob['dbId'] ?? 0);
        if ($selectedJobDbId > 0) {
            $productsQuery = DB::table('scanner_import_products')
                ->where('scanner_import_job_id', $selectedJobDbId);

            if ($scannerMode === 'priced') {
                $productsQuery->where('price_value', '>', 0);
            }

            if ($q !== '') {
                $productsQuery->where(function ($query) use ($q) {
                    $query->where('name', 'like', '%'.$q.'%')
                        ->orWhere('product_code', 'like', '%'.$q.'%')
                        ->orWhere('url', 'like', '%'.$q.'%')
                        ->orWhere('source_url', 'like', '%'.$q.'%')
                        ->orWhere('price_text', 'like', '%'.$q.'%');
                });
            }

            $scannerProductIds = (clone $productsQuery)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $scannerProducts = $productsQuery
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();

            $scannerProducts->getCollection()->transform(function ($product) {
                $name = (string) ($product->name ?? '');
                $url = (string) ($product->url ?? '');
                $sourceUrl = (string) ($product->source_url ?? '');

                return [
                    'id' => (string) ($product->external_id ?? ''),
                    'dbId' => (int) ($product->id ?? 0),
                    'name' => $name,
                    'price' => (string) ($product->price_text ?? ''),
                    'priceValue' => (int) ($product->price_value ?? 0),
                    'currency' => (string) ($product->currency ?? ''),
                    'url' => $url,
                    'sourceUrl' => $sourceUrl,
                    'productCode' => ProductCodeExtractor::best((string) ($product->product_code ?? ''), $name, $url, $sourceUrl),
                    'updatedAt' => (string) ($product->updated_at ?? ''),
                ];
            });
        }

        return view('dashboard.quick-scan', [
            'importEndpoint' => url('/api/products/import'),
            'apiKeyConfigured' => trim((string) config('services.checkgia_import.api_key', '')) !== '',
            'scannerError' => $scannerError,
            'scannerJobs' => $scannerJobs,
            'selectedScannerJob' => $selectedScannerJob,
            'selectedJobId' => $selectedJobId,
            'websiteUrl' => $websiteUrl,
            'scanRequest' => $scanRequest,
            'scannerProductIds' => $scannerProductIds,
            'scannerProducts' => $scannerProducts,
            'scannerMode' => $scannerMode,
            'q' => $q,
            'perPage' => $perPage,
        ]);
    }

    public function requestScan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'website_url' => ['required', 'url', 'max:2048'],
        ]);

        if (! Schema::hasTable('scanner_scan_requests')) {
            return redirect()
                ->route('dashboard.quick-scan', ['website_url' => $validated['website_url']])
                ->withErrors(['website_url' => 'Chua co bang hang doi quet. Hay chay migration tren hosting.']);
        }

        $websiteUrl = $this->normalizeWebsiteUrl((string) $validated['website_url']);
        $websiteKey = $this->websiteKey($websiteUrl);
        if ($websiteKey === '') {
            return redirect()
                ->route('dashboard.quick-scan', ['website_url' => $validated['website_url']])
                ->withErrors(['website_url' => 'Link website khong hop le.']);
        }

        $existing = DB::table('scanner_scan_requests')->where('url_key', $websiteKey)->first();
        $now = now();

        if ($existing) {
            $activeStatuses = ['pending', 'claimed', 'running'];
            $status = in_array((string) ($existing->status ?? ''), $activeStatuses, true)
                ? (string) $existing->status
                : 'pending';

            DB::table('scanner_scan_requests')
                ->where('id', (int) $existing->id)
                ->update([
                    'requested_by_user_id' => $request->user()?->id,
                    'requested_url' => $websiteUrl,
                    'status' => $status,
                    'requested_at' => $status === 'pending' ? $now : ($existing->requested_at ?? $now),
                    'failed_at' => null,
                    'error' => null,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('scanner_scan_requests')->insert([
                'requested_by_user_id' => $request->user()?->id,
                'requested_url' => $websiteUrl,
                'url_key' => $websiteKey,
                'status' => 'pending',
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return redirect()
            ->route('dashboard.quick-scan', ['website_url' => $websiteUrl])
            ->with('status', 'Da gui lenh quet website. Vui long cho khoang 1 ngay de phan mem Windows quet va day du lieu len Check Gia.');
    }

    public function addToCompare(Request $request): RedirectResponse
    {
        if ($request->user()->isViewer()) {
            abort(403);
        }

        $validated = $request->validate([
            'scanner_product_ids_json' => ['nullable', 'string', 'max:500000'],
            'scanner_product_ids' => ['nullable', 'array', 'max:20000'],
            'scanner_product_ids.*' => ['nullable', 'integer', 'min:1'],
            'website_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if (! Schema::hasTable('scanner_import_products') || ! Schema::hasTable('scanner_import_jobs')) {
            return back()->with('status', 'Chua co du lieu quet de them vao bang so sanh.');
        }

        $ids = [];
        $idsJson = trim((string) ($validated['scanner_product_ids_json'] ?? ''));
        if ($idsJson !== '') {
            $decoded = json_decode($idsJson, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            }
        }
        if ($ids === []) {
            $ids = $validated['scanner_product_ids'] ?? [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return back()->with('status', 'Hay chon it nhat 1 san pham.');
        }

        $websiteKey = $this->websiteKey($this->normalizeWebsiteUrl((string) ($validated['website_url'] ?? '')));
        $rows = DB::table('scanner_import_products')
            ->join('scanner_import_jobs', 'scanner_import_jobs.id', '=', 'scanner_import_products.scanner_import_job_id')
            ->whereIn('scanner_import_products.id', $ids)
            ->select([
                'scanner_import_products.id',
                'scanner_import_products.name',
                'scanner_import_products.product_code',
                'scanner_import_products.price_value',
                'scanner_import_products.url',
                'scanner_import_products.updated_at',
                'scanner_import_jobs.start_url',
            ])
            ->get()
            ->filter(function ($row) use ($websiteKey) {
                if ($websiteKey === '') {
                    return true;
                }

                return $this->websiteKey((string) ($row->start_url ?? '')) === $websiteKey;
            })
            ->values();

        if ($rows->isEmpty()) {
            return back()->with('status', 'Khong tim thay san pham da chon trong du lieu quet hien tai.');
        }

        $userId = $request->user()->effectiveUserId();
        $limit = User::resolveProductLimitById($userId);
        $used = (int) Product::query()->where('user_id', $userId)->count()
            + (int) ShopeeProduct::query()->where('user_id', $userId)->count();
        $remaining = max(0, $limit - $used);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $now = now();
        $reachedLimit = false;

        DB::transaction(function () use ($rows, $userId, &$created, &$updated, &$skipped, &$remaining, &$reachedLimit, $now): void {
            foreach ($rows as $row) {
                $url = trim((string) ($row->url ?? ''));
                if ($url === '') {
                    $skipped++;
                    continue;
                }

                $name = trim((string) ($row->name ?? ''));
                if ($name === '') {
                    $name = trim((string) ($row->product_code ?? ''));
                }
                if ($name === '') {
                    $name = $this->guessProductNameFromUrl($url);
                }

                $price = max(0, (int) ($row->price_value ?? 0));
                $product = Product::query()
                    ->where('user_id', $userId)
                    ->where('product_url', $url)
                    ->first();

                if (! $product) {
                    if ($remaining <= 0) {
                        $reachedLimit = true;
                        $skipped++;
                        continue;
                    }

                    $product = Product::create([
                        'user_id' => $userId,
                        'name' => mb_substr($name, 0, 255),
                        'price' => $price,
                        'product_url' => $url,
                        'last_scraped_at' => $now,
                    ]);
                    $created++;
                    $remaining--;
                } else {
                    $product->name = mb_substr($name, 0, 255);
                    $product->price = $price;
                    $product->last_scraped_at = $now;
                    $product->save();
                    $updated++;
                }

                if ($price > 0) {
                    $latest = ProductPriceHistory::query()
                        ->where('product_id', $product->id)
                        ->latest('fetched_at')
                        ->first();

                    if (! $latest || (int) $latest->price !== $price) {
                        ProductPriceHistory::create([
                            'product_id' => $product->id,
                            'price' => $price,
                            'fetched_at' => $now,
                        ]);
                    }
                }
            }
        });

        $message = 'Da them '.$created.' san pham vao bang so sanh';
        if ($updated > 0) {
            $message .= ', cap nhat '.$updated.' san pham da co';
        }
        if ($skipped > 0) {
            $message .= ', bo qua '.$skipped.' san pham';
        }
        if ($reachedLimit) {
            $message .= '. Ban da den gioi han so sanh '.$limit.' san pham.';
        }

        return redirect()->to(route('dashboard').'#comparisonCard')->with('status', $message);
    }

    private function normalizeWebsiteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return $url;
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

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return '';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = trim((string) ($parts['path'] ?? ''), '/');

        return $host.($path !== '' ? '/'.$path : '');
    }

    private function legacyIndex(Request $request): View
    {
        $authUser = $request->user();
        $userId = $authUser->effectiveUserId();

        $perPage = 50;
        if (Schema::hasColumn('users', 'quick_scan_per_page')) {
            $stored = User::query()->whereKey($authUser->id)->value('quick_scan_per_page');
            $storedInt = is_null($stored) ? null : (int) $stored;
            if (in_array($storedInt, [25, 50, 100, 200], true)) {
                $perPage = $storedInt;
            }
        }

        $perPageRaw = trim((string) $request->query('per_page', ''));
        if ($perPageRaw !== '' && ctype_digit($perPageRaw)) {
            $pp = (int) $perPageRaw;
            if (in_array($pp, [25, 50, 100, 200], true)) {
                $perPage = $pp;
                if (Schema::hasColumn('users', 'quick_scan_per_page')) {
                    User::query()->whereKey($authUser->id)->update(['quick_scan_per_page' => $pp]);
                }
            }
        }

        $runId = trim((string) $request->query('run', ''));
        $run = null;
        if ($runId !== '' && ctype_digit($runId)) {
            $run = DB::table('quick_scan_runs')
                ->where('user_id', $userId)
                ->where('id', (int) $runId)
                ->first();
        }
        if (! $run) {
            $run = DB::table('quick_scan_runs')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->first();
        }

        $q = trim((string) $request->query('q', ''));
        $hasPrice = (string) $request->query('has_price', '') === '1';
        $items = null;
        $scanWarning = null;
        $scanSampleUrls = [];

        if ($run) {
            $itemsQuery = DB::table('quick_scan_items')
                ->where('run_id', (int) $run->id)
                ->where('is_product', 1);

            if ($hasPrice) {
                $itemsQuery->whereNotNull('price')->where('price', '>', 0);
            }

            if ($q !== '') {
                $itemsQuery->where(function ($qq) use ($q) {
                    $qq->where('url', 'like', '%'.$q.'%')
                        ->orWhere('name', 'like', '%'.$q.'%')
                        ->orWhere('name_guess', 'like', '%'.$q.'%');
                });
            }

            /** @var LengthAwarePaginator $items */
            $items = $itemsQuery
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();

            $found = (int) ($run->found_urls ?? 0);
            $processed = (int) ($run->processed_count ?? 0);
            $products = (int) ($run->product_count ?? 0);
            if ($found > 0 && $processed >= $found && $products === 0) {
                $scanWarning = 'Không lấy được tên sản phẩm từ bất kỳ link nào. Thường do XPath tên/giá không khớp với website shop hoặc website đang chặn request từ server.';
                $scanSampleUrls = DB::table('quick_scan_items')
                    ->where('run_id', (int) $run->id)
                    ->orderBy('id')
                    ->limit(5)
                    ->pluck('url')
                    ->all();
            }
        }

        return view('dashboard.quick-scan', [
            'baseUrl' => (string) old('base_url', (string) $request->query('base_url', '')),
            'sitemapUrl' => (string) old('sitemap_url', (string) $request->query('sitemap_url', '')),
            'limit' => (int) old('limit', (int) $request->query('limit', 2000)),
            'scanMessage' => null,
            'run' => $run,
            'items' => $items,
            'q' => $q,
            'perPage' => $perPage,
            'hasPrice' => $hasPrice,
            'scanWarning' => $scanWarning,
            'scanSampleUrls' => $scanSampleUrls,
        ]);
    }

    public function scan(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'base_url' => ['required', 'url', 'max:2048'],
            'sitemap_url' => ['nullable', 'url', 'max:2048'],
            'limit' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $baseUrl = trim((string) $validated['base_url']);
        $sitemapUrl = trim((string) ($validated['sitemap_url'] ?? ''));
        $limit = (int) $validated['limit'];

        $parsedScheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $parsedHost = parse_url($baseUrl, PHP_URL_HOST);
        $parsedPort = parse_url($baseUrl, PHP_URL_PORT);
        $scheme = is_string($parsedScheme) && $parsedScheme !== '' ? strtolower($parsedScheme) : 'https';
        $baseHost = is_string($parsedHost) ? strtolower($parsedHost) : '';
        $port = is_int($parsedPort) ? $parsedPort : null;
        $origin = $baseHost ? ($scheme.'://'.$baseHost.($port ? ':'.$port : '')) : rtrim($baseUrl, '/');

        $timeoutSeconds = 7;
        if (Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'website_scrape_timeout_seconds')) {
            try {
                $timeoutSeconds = max(1, (int) (\App\Models\AppSetting::current()?->website_scrape_timeout_seconds ?? 7));
            } catch (\Throwable) {
            }
        }

        $req = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept' => 'application/xml,text/xml,application/xhtml+xml,text/html;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->connectTimeout($timeoutSeconds)->timeout($timeoutSeconds)->retry(1, 100);

        $candidates = [];
        if ($sitemapUrl !== '') {
            $candidates[] = $sitemapUrl;
        } else {
            $robotsUrl = rtrim($origin, '/').'/robots.txt';
            try {
                $res = $req->get($robotsUrl);
                if ($res->successful()) {
                    $robots = (string) $res->body();
                    $lines = preg_split('/\R+/', $robots) ?: [];
                    foreach ($lines as $line) {
                        $line = trim((string) $line);
                        if ($line === '' || str_starts_with($line, '#')) {
                            continue;
                        }
                        if (preg_match('/^sitemap\s*:\s*(\S+)/i', $line, $m) === 1) {
                            $u = trim((string) $m[1]);
                            if ($u === '') {
                                continue;
                            }
                            if (! str_starts_with($u, 'http://') && ! str_starts_with($u, 'https://')) {
                                continue;
                            }
                            $host = parse_url($u, PHP_URL_HOST);
                            $host = is_string($host) ? strtolower($host) : '';
                            if ($baseHost !== '' && $host !== '' && $host !== $baseHost) {
                                continue;
                            }
                            $candidates[] = $u;
                        }
                    }
                }
            } catch (\Throwable) {
            }

            $originBase = rtrim($origin, '/');
            $candidates[] = $originBase.'/sitemap.xml';
            $candidates[] = $originBase.'/sitemap_index.xml';
            $candidates[] = $originBase.'/sitemap.xml.gz';
        }

        $candidates = array_values(array_unique(array_values(array_filter(array_map(fn ($v) => trim((string) $v), $candidates)))));

        $decodeSitemapBody = function (string $url, string $body): ?string {
            $body = (string) $body;
            if ($body === '') {
                return null;
            }
            if (strlen($body) > 10 * 1024 * 1024) {
                return null;
            }
            if (str_ends_with(strtolower($url), '.gz')) {
                $decoded = @gzdecode($body);
                if (! is_string($decoded) || $decoded === '') {
                    return null;
                }
                if (strlen($decoded) > 10 * 1024 * 1024) {
                    return null;
                }

                return $decoded;
            }

            return $body;
        };

        $scanMessage = null;
        $rawXml = null;
        $usedSitemap = null;
        foreach ($candidates as $u) {
            try {
                $res = $req->get($u);
                if (! $res->successful()) {
                    continue;
                }
                $body = $decodeSitemapBody($u, (string) $res->body());
                if (! is_string($body) || trim($body) === '') {
                    continue;
                }
                $rawXml = $body;
                $usedSitemap = $u;
                break;
            } catch (\Throwable) {
            }
        }

        $urls = [];
        $visited = [];
        $pushUrl = function (string $url) use (&$urls, &$visited, $baseHost, $limit) {
            $url = trim($url);
            if ($url === '') return;
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) return;
            $host = parse_url($url, PHP_URL_HOST);
            $host = is_string($host) ? strtolower($host) : '';
            if ($baseHost !== '' && $host !== '' && $host !== $baseHost) return;
            if (isset($visited[$url])) return;
            $visited[$url] = true;
            $urls[] = $url;
            if (count($urls) >= $limit) return;
        };

        $parseXml = function (string $xml) {
            libxml_use_internal_errors(true);
            $sx = simplexml_load_string($xml);
            libxml_clear_errors();
            return $sx ?: null;
        };

        $fetchAndCollect = function (string $url) use (&$req, &$visited, &$urls, $limit, $baseHost, $parseXml, $pushUrl, &$fetchAndCollect) {
            $url = trim($url);
            if ($url === '' || isset($visited['__sitemap:'.$url])) {
                return;
            }
            $visited['__sitemap:'.$url] = true;
            try {
                $res = $req->get($url);
                if (! $res->successful()) {
                    return;
                }
                $bodyRaw = (string) $res->body();
                if (trim($bodyRaw) === '') {
                    return;
                }
                $body = str_ends_with(strtolower($url), '.gz') ? (@gzdecode($bodyRaw) ?: '') : $bodyRaw;
                if (! is_string($body) || trim($body) === '') {
                    return;
                }
                if (strlen($body) > 10 * 1024 * 1024) {
                    return;
                }
                $sx = $parseXml($body);
                if (! $sx) {
                    return;
                }
                $rootName = strtolower($sx->getName());

                if ($rootName === 'sitemapindex') {
                    $i = 0;
                    foreach ($sx->sitemap as $sm) {
                        $loc = trim((string) ($sm->loc ?? ''));
                        if ($loc === '') continue;
                        $host = parse_url($loc, PHP_URL_HOST);
                        $host = is_string($host) ? strtolower($host) : '';
                        if ($baseHost !== '' && $host !== '' && $host !== $baseHost) continue;
                        $fetchAndCollect($loc);
                        $i++;
                        if ($i >= 30 || count($urls) >= $limit) break;
                    }
                    return;
                }

                if ($rootName === 'urlset') {
                    foreach ($sx->url as $u) {
                        $loc = trim((string) ($u->loc ?? ''));
                        if ($loc === '') continue;
                        $pushUrl($loc);
                        if (count($urls) >= $limit) break;
                    }
                }
            } catch (\Throwable) {
            }
        };

        if (is_string($rawXml) && $rawXml !== '') {
            $sx = $parseXml($rawXml);
            if ($sx) {
                $rootName = strtolower($sx->getName());
                if ($rootName === 'sitemapindex') {
                    $visited['__sitemap:'.$usedSitemap] = true;
                    $i = 0;
                    foreach ($sx->sitemap as $sm) {
                        $loc = trim((string) ($sm->loc ?? ''));
                        if ($loc === '') continue;
                        $fetchAndCollect($loc);
                        $i++;
                        if ($i >= 30 || count($urls) >= $limit) break;
                    }
                    $scanMessage = $usedSitemap ? 'Đã quét sitemap: '.$usedSitemap : null;
                } elseif ($rootName === 'urlset') {
                    foreach ($sx->url as $u) {
                        $loc = trim((string) ($u->loc ?? ''));
                        if ($loc === '') continue;
                        $pushUrl($loc);
                        if (count($urls) >= $limit) break;
                    }
                    $scanMessage = $usedSitemap ? 'Đã quét sitemap: '.$usedSitemap : null;
                } else {
                    $scanMessage = 'Sitemap không đúng định dạng XML urlset/sitemapindex.';
                }
            } else {
                $scanMessage = 'Không đọc được sitemap (XML lỗi hoặc bị chặn).';
            }
        } else {
            $scanMessage = 'Không tìm thấy sitemap hoặc không truy cập được.';
        }

        if (($sitemapUrl === '') && $usedSitemap === null && $urls === []) {
            return redirect()
                ->route('dashboard.quick-scan')
                ->withInput()
                ->withErrors(['sitemap_url' => 'Không tự tìm thấy sitemap. Vui lòng nhập link sitemap (xem robots.txt hoặc thử /sitemap.xml).']);
        }

        $userId = $request->user()->effectiveUserId();
        $runId = null;

        $setting = UserScrapeSetting::query()->where('user_id', $userId)->first();
        if (! $setting || ! $setting->own_name_xpath || ! $setting->own_price_xpath) {
            return redirect()
                ->route('dashboard.quick-scan')
                ->withInput()
                ->withErrors(['base_url' => 'Chưa cấu hình XPath tên/giá của bạn. Vui lòng vào “Cài đặt” để nhập XPath trước khi quét.']);
        }

        DB::transaction(function () use ($userId, $baseUrl, $usedSitemap, $urls, &$runId) {
            $runId = (int) DB::table('quick_scan_runs')->insertGetId([
                'user_id' => $userId,
                'base_url' => $baseUrl,
                'sitemap_url' => $usedSitemap,
                'found_urls' => count($urls),
                'status' => 'running',
                'stop_requested' => 0,
                'processed_count' => 0,
                'product_count' => 0,
                'priced_count' => 0,
                'started_at' => now(),
                'finished_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            $batch = [];
            foreach ($urls as $url) {
                $url = trim((string) $url);
                if ($url === '') {
                    continue;
                }
                $batch[] = [
                    'run_id' => $runId,
                    'url_hash' => sha1($url),
                    'url' => $url,
                    'name_guess' => $this->guessProductNameFromUrl($url),
                    'name' => null,
                    'price' => null,
                    'fetched_at' => null,
                    'is_product' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($batch) >= 500) {
                    DB::table('quick_scan_items')->insertOrIgnore($batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                DB::table('quick_scan_items')->insertOrIgnore($batch);
            }
        });

        if ($runId) {
            dispatch(new QuickScanScrapeRun((int) $runId));
        }

        return redirect()->route('dashboard.quick-scan', [
            'run' => $runId,
        ])->with('status', $scanMessage ? ($scanMessage.' Đang lấy tên/giá...') : 'Đang quét và lấy tên/giá...');
    }

    public function tick(Request $request, int $run): JsonResponse
    {
        $userId = $request->user()->effectiveUserId();

        $runRow = DB::table('quick_scan_runs')
            ->where('user_id', $userId)
            ->where('id', $run)
            ->first();

        if (! $runRow) {
            return response()->json(['ok' => false, 'message' => 'Run không tồn tại.'], 404);
        }

        if ((int) ($runRow->stop_requested ?? 0) === 1) {
            DB::table('quick_scan_runs')
                ->where('id', $run)
                ->update([
                    'status' => 'paused',
                    'updated_at' => now(),
                ]);

            return response()->json([
                'ok' => true,
                'status' => 'paused',
                'processed_count' => (int) ($runRow->processed_count ?? 0),
                'product_count' => (int) ($runRow->product_count ?? 0),
                'priced_count' => (int) ($runRow->priced_count ?? 0),
                'found_urls' => (int) ($runRow->found_urls ?? 0),
            ]);
        }

        $setting = UserScrapeSetting::query()->where('user_id', $userId)->first();
        if (! $setting || ! $setting->own_name_xpath || ! $setting->own_price_xpath) {
            return response()->json(['ok' => false, 'message' => 'Chưa cấu hình XPath tên/giá.'], 422);
        }

        $batch = DB::table('quick_scan_items')
            ->where('run_id', $run)
            ->whereNull('fetched_at')
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'url'])
            ->all();

        if ($batch === []) {
            DB::table('quick_scan_runs')
                ->where('id', $run)
                ->update([
                    'status' => 'done',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'ok' => true,
                'status' => 'done',
                'processed_count' => (int) ($runRow->processed_count ?? 0),
                'product_count' => (int) ($runRow->product_count ?? 0),
                'priced_count' => (int) ($runRow->priced_count ?? 0),
                'found_urls' => (int) ($runRow->found_urls ?? 0),
            ]);
        }

        $timeoutSeconds = 7;
        $concurrency = 5;
        try {
            if (Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'website_scrape_timeout_seconds')) {
                $timeoutSeconds = max(1, (int) (\App\Models\AppSetting::current()?->website_scrape_timeout_seconds ?? 7));
            }
            if (Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'website_scrape_concurrency')) {
                $concurrency = max(1, (int) (\App\Models\AppSetting::current()?->website_scrape_concurrency ?? 5));
                $concurrency = min(10, $concurrency);
            }
        } catch (\Throwable) {
        }

        $scraper = new PriceScraper(timeoutSeconds: $timeoutSeconds, connectTimeoutSeconds: $timeoutSeconds);

        $nameXpaths = array_merge(
            [(string) $setting->own_name_xpath],
            \App\Models\UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'name')->orderBy('position')->pluck('xpath')->all()
        );
        $priceXpaths = array_merge(
            [(string) $setting->own_price_xpath],
            \App\Models\UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'price')->orderBy('position')->pluck('xpath')->all()
        );

        $urlsByKey = [];
        $skipIds = [];
        foreach ($batch as $row) {
            $id = (int) ($row->id ?? 0);
            $url = trim((string) ($row->url ?? ''));
            if ($id > 0 && $url !== '' && $this->shouldSkipNonProductUrl($url)) {
                $skipIds[] = $id;
                continue;
            }
            if ($id > 0 && $url !== '') {
                $urlsByKey[(string) $id] = $url;
            }
        }

        if ($skipIds !== []) {
            $now = now();
            DB::table('quick_scan_items')
                ->whereIn('id', $skipIds)
                ->update([
                    'name' => null,
                    'price' => null,
                    'fetched_at' => $now,
                    'is_product' => 0,
                    'updated_at' => $now,
                ]);
            DB::table('quick_scan_runs')
                ->where('id', $run)
                ->update([
                    'processed_count' => DB::raw('processed_count + '.(int) count($skipIds)),
                    'updated_at' => now(),
                ]);
        }

        if ($urlsByKey === []) {
            $runAfter = DB::table('quick_scan_runs')->where('id', $run)->first(['processed_count', 'product_count', 'priced_count', 'found_urls']);
            $done = $runAfter && (int) ($runAfter->processed_count ?? 0) >= (int) ($runAfter->found_urls ?? 0);
            if ($done) {
                DB::table('quick_scan_runs')
                    ->where('id', $run)
                    ->update([
                        'status' => 'done',
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            return response()->json([
                'ok' => true,
                'status' => $done ? 'done' : 'running',
                'processed_count' => (int) ($runAfter->processed_count ?? 0),
                'product_count' => (int) ($runAfter->product_count ?? 0),
                'priced_count' => (int) ($runAfter->priced_count ?? 0),
                'found_urls' => (int) ($runAfter->found_urls ?? 0),
            ]);
        }

        try {
            $htmlByKey = $scraper->fetchHtmlPool($urlsByKey, $concurrency);
        } catch (\Throwable) {
            $htmlByKey = [];
        }

        $now = now();
        $processed = 0;
        $products = 0;
        $priced = 0;

        foreach ($urlsByKey as $id => $url) {
            $html = $htmlByKey[$id] ?? null;
            if (! is_string($html) || trim($html) === '') {
                try {
                    $html = $scraper->fetchHtml($url);
                } catch (\Throwable) {
                    $html = null;
                }
            }
            $name = null;
            $price = null;
            $isProduct = false;

            if (is_string($html) && trim($html) !== '') {
                $name = $scraper->extractFirstByXPaths($html, $nameXpaths);
                if (! $name) {
                    $name = $scraper->extractTitle($html);
                }
                $priceRaw = $scraper->extractFirstByXPaths($html, $priceXpaths);
                $price = $scraper->parsePriceToInt($priceRaw, (string) ($setting->price_regex ?? null));

                $hasName = is_string($name) && trim($name) !== '';
                $hasPrice = ! is_null($price) && (int) $price > 0;
                $isProduct = $hasName && $hasPrice;
                if ($hasName) {
                    $products++;
                }
                if ($hasPrice) {
                    $priced++;
                }
            }

            DB::table('quick_scan_items')
                ->where('id', (int) $id)
                ->update([
                    'name' => $name ? mb_substr(trim((string) $name), 0, 255) : null,
                    'price' => $price,
                    'fetched_at' => $now,
                    'is_product' => $isProduct ? 1 : 0,
                    'updated_at' => $now,
                ]);
            $processed++;
        }

        DB::table('quick_scan_runs')
            ->where('id', $run)
            ->update([
                'status' => 'running',
                'processed_count' => DB::raw('processed_count + '.(int) $processed),
                'product_count' => DB::raw('product_count + '.(int) $products),
                'priced_count' => DB::raw('priced_count + '.(int) $priced),
                'updated_at' => now(),
            ]);

        $runAfter = DB::table('quick_scan_runs')->where('id', $run)->first(['processed_count', 'product_count', 'priced_count', 'found_urls']);
        $done = $runAfter && (int) ($runAfter->processed_count ?? 0) >= (int) ($runAfter->found_urls ?? 0);
        if ($done) {
            DB::table('quick_scan_runs')
                ->where('id', $run)
                ->update([
                    'status' => 'done',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'ok' => true,
            'status' => $done ? 'done' : 'running',
            'processed_count' => (int) ($runAfter->processed_count ?? 0),
            'product_count' => (int) ($runAfter->product_count ?? 0),
            'priced_count' => (int) ($runAfter->priced_count ?? 0),
            'found_urls' => (int) ($runAfter->found_urls ?? 0),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer'],
            'urls' => ['array'],
            'urls.*' => ['nullable', 'url', 'max:2048'],
        ]);

        $userId = $request->user()->effectiveUserId();
        $runId = (int) $validated['run_id'];

        $run = DB::table('quick_scan_runs')->where('user_id', $userId)->where('id', $runId)->first();
        if (! $run) {
            return redirect()->route('dashboard.quick-scan')->withErrors(['run_id' => 'Phiên quét không tồn tại hoặc không thuộc tài khoản của bạn.']);
        }

        $limit = User::resolveProductLimitById($userId);
        $currentCount = Product::query()->where('user_id', $userId)->count();
        $remaining = max(0, $limit - $currentCount);

        $urls = $validated['urls'] ?? [];
        $clean = [];
        foreach ($urls as $u) {
            $u = is_string($u) ? trim($u) : '';
            if ($u === '') continue;
            $clean[$u] = true;
        }
        $cleanUrls = array_keys($clean);

        if ($cleanUrls === []) {
            return redirect()->route('dashboard.quick-scan', ['run' => $runId])->withErrors(['urls' => 'Vui lòng chọn ít nhất 1 sản phẩm.']);
        }

        $allowed = DB::table('quick_scan_items')
            ->where('run_id', $runId)
            ->where('is_product', 1)
            ->whereIn('url', $cleanUrls)
            ->pluck('url')
            ->all();
        $allowedSet = array_fill_keys(array_map('strval', $allowed), true);

        $cleanUrls = array_values(array_filter($cleanUrls, fn ($u) => isset($allowedSet[$u])));
        if ($cleanUrls === []) {
            return redirect()->route('dashboard.quick-scan', ['run' => $runId])->withErrors(['urls' => 'Không tìm thấy sản phẩm hợp lệ trong phiên quét.']);
        }

        $created = 0;
        $skippedExists = 0;
        $skippedInvalid = count($urls) - count($cleanUrls);
        $reachedLimit = false;
        $productIdsToScrape = [];

        foreach ($cleanUrls as $url) {
            if ($remaining <= 0) {
                $reachedLimit = true;
                break;
            }

            $exists = Product::query()
                ->where('user_id', $userId)
                ->where('product_url', $url)
                ->exists();
            if ($exists) {
                $skippedExists++;
                continue;
            }

            $p = Product::create([
                'user_id' => $userId,
                'name' => $this->guessProductNameFromUrl($url),
                'price' => 0,
                'product_url' => $url,
            ]);
            $created++;
            $remaining--;
            $productIdsToScrape[] = (int) $p->id;
        }

        $productIdsToScrape = array_values(array_unique($productIdsToScrape));
        foreach ($productIdsToScrape as $id) {
            ScrapeProductPrices::dispatch($id);
        }

        $msg = 'Đã thêm '.$created.' sản phẩm vào danh sách';
        if ($skippedExists > 0) {
            $msg .= '. Bỏ qua '.$skippedExists.' link đã có';
        }
        if ($skippedInvalid > 0) {
            $msg .= '. Bỏ qua '.$skippedInvalid.' link không hợp lệ/khác domain';
        }
        if ($reachedLimit) {
            $msg .= '. Bạn đã đến giới hạn so sánh '.$limit.' sản phẩm, để dùng tiếp hãy xóa bớt sản phẩm so sánh hoặc liên hệ admin để nâng cấp tài khoản';
        }

        return redirect()->route('dashboard')->with('status', $msg);
    }

    public function pause(Request $request, int $run): RedirectResponse
    {
        $userId = $request->user()->effectiveUserId();
        DB::table('quick_scan_runs')
            ->where('user_id', $userId)
            ->where('id', $run)
            ->update([
                'stop_requested' => 1,
                'status' => 'pausing',
                'updated_at' => now(),
            ]);

        return redirect()->route('dashboard.quick-scan', ['run' => $run])->with('status', 'Đã gửi yêu cầu dừng. Hệ thống sẽ dừng sau khi xử lý xong batch hiện tại.');
    }

    public function resume(Request $request, int $run): RedirectResponse
    {
        $userId = $request->user()->effectiveUserId();
        $updated = DB::table('quick_scan_runs')
            ->where('user_id', $userId)
            ->where('id', $run)
            ->update([
                'stop_requested' => 0,
                'status' => 'running',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated) {
            dispatch(new QuickScanScrapeRun($run));
        }

        return redirect()->route('dashboard.quick-scan', ['run' => $run])->with('status', 'Đã tiếp tục quét.');
    }

    private function guessProductNameFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? trim($host) : '';
        $host = preg_replace('/:\\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\\./', '', $host) ?? $host;
        $host = $host !== '' ? $host : 'Sản phẩm quét';

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? trim($path) : '';
        $tail = $path !== '' ? trim(basename($path), " \t\n\r\0\x0B/") : '';
        if ($tail !== '') {
            $tail = mb_substr($tail, 0, 60);
            $host .= ' - '.$tail;
        }

        return mb_substr($host, 0, 255);
    }
}
