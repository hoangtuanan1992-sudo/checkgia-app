<?php

namespace App\Http\Controllers;

use App\Services\ProductCodeExtractor;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ScannedProductFullController extends Controller
{
    public function __invoke(Request $request): View
    {
        $websiteInput = $this->websiteInput($request);
        $websiteUrl = $websiteInput !== '' ? $this->normalizeWebsiteUrl($websiteInput) : '';
        $websiteKey = $this->websiteKey($websiteUrl);
        $q = trim((string) $request->query('q', ''));
        $perPage = $this->perPage($request);
        $error = null;
        $selectedJob = null;
        $products = new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => $request->url(), 'query' => $this->queryForLinks($request, $websiteUrl)]
        );

        if (! Schema::hasTable('scanner_import_jobs') || ! Schema::hasTable('scanner_import_products')) {
            $error = 'Chưa có bảng dữ liệu quét. Hãy chạy migration import trên hosting.';

            return view('scanner.full-products', compact('websiteUrl', 'websiteKey', 'q', 'perPage', 'error', 'selectedJob', 'products'));
        }

        if ($websiteUrl !== '' && $websiteKey === '') {
            $error = 'Link website không hợp lệ.';
        }

        if ($websiteKey !== '') {
            $selectedJob = DB::table('scanner_import_jobs')
                ->orderByDesc('last_pushed_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->first(fn ($job): bool => $this->websiteKey((string) ($job->start_url ?? '')) === $websiteKey);

            if ($selectedJob) {
                $productsQuery = DB::table('scanner_import_products')
                    ->where('scanner_import_job_id', (int) $selectedJob->id);

                if ($q !== '') {
                    $productsQuery->where(function ($query) use ($q) {
                        $query->where('name', 'like', '%'.$q.'%')
                            ->orWhere('product_code', 'like', '%'.$q.'%')
                            ->orWhere('url', 'like', '%'.$q.'%')
                            ->orWhere('source_url', 'like', '%'.$q.'%')
                            ->orWhere('price_text', 'like', '%'.$q.'%');
                    });
                }

                $products = $productsQuery
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->appends($this->queryForLinks($request, $websiteUrl));

                $products->getCollection()->transform(function ($product) {
                    $name = (string) ($product->name ?? '');
                    $url = (string) ($product->url ?? '');
                    $sourceUrl = (string) ($product->source_url ?? '');

                    return [
                        'dbId' => (int) ($product->id ?? 0),
                        'name' => $name,
                        'productCode' => ProductCodeExtractor::best((string) ($product->product_code ?? ''), $name, $url, $sourceUrl),
                        'priceText' => (string) ($product->price_text ?? ''),
                        'priceValue' => (int) ($product->price_value ?? 0),
                        'url' => $url,
                        'sourceUrl' => $sourceUrl,
                        'updatedAt' => (string) ($product->updated_at ?? ''),
                    ];
                });
            }
        }

        return view('scanner.full-products', compact('websiteUrl', 'websiteKey', 'q', 'perPage', 'error', 'selectedJob', 'products'));
    }

    private function websiteInput(Request $request): string
    {
        foreach (['website_url', 'url', 'link'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        $raw = trim((string) $request->getQueryString());
        foreach (preg_split('/&+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $key = rawurldecode((string) strtok($part, '='));
            if ($this->looksLikeWebsite($key)) {
                return $key;
            }
        }

        foreach ($request->query() as $key => $value) {
            if ((string) $value !== '') {
                continue;
            }

            $key = rawurldecode((string) $key);
            if ($this->looksLikeWebsite($key)) {
                return $key;
            }
        }

        if ($raw === '' || str_contains($raw, '=')) {
            return '';
        }

        return rawurldecode($raw);
    }

    private function looksLikeWebsite(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return true;
        }

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(?:\/.*)?$/i', $value) === 1;
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
        $raw = trim((string) $request->query('per_page', ''));
        $value = ctype_digit($raw) ? (int) $raw : 200;

        return in_array($value, [50, 100, 200, 500], true) ? $value : 200;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryForLinks(Request $request, string $websiteUrl): array
    {
        $query = [];
        if ($websiteUrl !== '') {
            $query['website_url'] = $websiteUrl;
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query['q'] = $q;
        }
        $perPage = $this->perPage($request);
        if ($perPage !== 200) {
            $query['per_page'] = $perPage;
        }

        return $query;
    }
}
