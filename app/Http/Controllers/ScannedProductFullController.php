<?php

namespace App\Http\Controllers;

use App\Services\ProductCodeExtractor;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ScannedProductFullController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $websiteInput = $this->websiteInput($request);
        $websiteUrl = $websiteInput !== '' ? $this->normalizeWebsiteUrl($websiteInput) : '';
        $websiteKey = $this->websiteKey($websiteUrl);
        $q = trim((string) $request->query('q', ''));
        $perPage = $this->perPage($request);
        $wantsJson = strtolower(trim((string) $request->query('format', ''))) === 'json';
        $error = null;
        $selectedJob = null;
        $products = new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => $request->url(), 'query' => $this->queryForLinks($request, $websiteUrl)]
        );

        if ($wantsJson && $websiteUrl === '') {
            return $this->jsonError('Vui long nhap website_url, url hoac link.', 422, $websiteUrl, $websiteKey);
        }

        if (! Schema::hasTable('scanner_import_jobs') || ! Schema::hasTable('scanner_import_products')) {
            $error = 'Chưa có bảng dữ liệu quét. Hãy chạy migration import trên hosting.';

            if ($wantsJson) {
                return $this->jsonError($error, 503, $websiteUrl, $websiteKey);
            }

            return view('scanner.full-products', compact('websiteUrl', 'websiteKey', 'q', 'perPage', 'error', 'selectedJob', 'products'));
        }

        if ($websiteUrl !== '' && $websiteKey === '') {
            $error = 'Link website không hợp lệ.';

            if ($wantsJson) {
                return $this->jsonError($error, 422, $websiteUrl, $websiteKey);
            }
        }

        if ($websiteKey !== '') {
            $selectedJob = DB::table('scanner_import_jobs')
                ->orderByDesc('last_pushed_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->first(fn ($job): bool => $this->websiteKey((string) ($job->start_url ?? '')) === $websiteKey);

            if ($selectedJob) {
                $productsQuery = $this->productsQuery((int) $selectedJob->id, $q);

                if ($wantsJson) {
                    $jsonProducts = $productsQuery
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($product): array => $this->jsonProductRow($product))
                        ->values();

                    $payload = [
                        'ok' => true,
                        'website' => $websiteUrl,
                        'websiteKey' => $websiteKey,
                        'jobId' => (string) ($selectedJob->external_job_id ?? ''),
                        'total' => $jsonProducts->count(),
                        'products' => $jsonProducts,
                    ];

                    if ($q !== '') {
                        $payload['q'] = $q;
                    }

                    return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                $products = $productsQuery
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->appends($this->queryForLinks($request, $websiteUrl));

                $products->getCollection()->transform(fn ($product): array => $this->htmlProductRow($product));
            }
        }

        if ($wantsJson) {
            return $this->jsonError('Website nay chua co du lieu quet.', 404, $websiteUrl, $websiteKey);
        }

        return view('scanner.full-products', compact('websiteUrl', 'websiteKey', 'q', 'perPage', 'error', 'selectedJob', 'products'));
    }

    private function productsQuery(int $jobId, string $q): Builder
    {
        $productsQuery = DB::table('scanner_import_products')
            ->where('scanner_import_job_id', $jobId);

        if ($q !== '') {
            $productsQuery->where(function ($query) use ($q) {
                $query->where('name', 'like', '%'.$q.'%')
                    ->orWhere('product_code', 'like', '%'.$q.'%')
                    ->orWhere('url', 'like', '%'.$q.'%')
                    ->orWhere('source_url', 'like', '%'.$q.'%')
                    ->orWhere('price_text', 'like', '%'.$q.'%');
            });
        }

        return $productsQuery;
    }

    /**
     * @return array{code: string, name: string, url: string}
     */
    private function jsonProductRow(object $product): array
    {
        $name = (string) ($product->name ?? '');
        $url = (string) (($product->url ?? '') ?: ($product->link ?? ''));
        $sourceUrl = (string) ($product->source_url ?? '');

        return [
            'code' => ProductCodeExtractor::best((string) ($product->product_code ?? ''), $name, $url, $sourceUrl),
            'name' => $name,
            'url' => $url,
        ];
    }

    /**
     * @return array{dbId: int, name: string, productCode: string, priceText: string, priceValue: int, url: string, sourceUrl: string, updatedAt: string}
     */
    private function htmlProductRow(object $product): array
    {
        $name = (string) ($product->name ?? '');
        $url = (string) (($product->url ?? '') ?: ($product->link ?? ''));
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
    }

    private function jsonError(string $error, int $status, string $websiteUrl, string $websiteKey): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => $error,
            'website' => $websiteUrl,
            'websiteKey' => $websiteKey,
            'total' => 0,
            'products' => [],
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
