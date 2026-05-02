<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $expectedKey = trim((string) config('services.checkgia_import.api_key', ''));
        if ($expectedKey === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Import API key is not configured.',
            ], 503);
        }

        $providedKey = $this->readApiKey($request);
        if ($providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid API key',
            ], 401);
        }

        $maxProducts = max(1, (int) config('services.checkgia_import.max_products_per_request', 5000));
        $validator = Validator::make($request->all(), [
            'source' => ['required', 'array'],
            'source.app' => ['nullable', 'string', 'max:100'],
            'source.jobId' => ['required', 'string', 'max:255'],
            'source.startUrl' => ['nullable', 'string', 'max:2048'],
            'source.mode' => ['nullable', 'string', 'max:50'],
            'source.productCount' => ['nullable', 'integer', 'min:0'],
            'source.batch' => ['nullable', 'array'],
            'source.batch.index' => ['nullable', 'integer', 'min:1'],
            'source.batch.total' => ['nullable', 'integer', 'min:1'],
            'source.batch.size' => ['nullable', 'integer', 'min:0'],
            'source.pushedAt' => ['nullable', 'date'],
            'products' => ['required', 'array', 'min:1', 'max:'.$maxProducts],
            'products.*' => ['required', 'array'],
            'products.*.externalId' => ['nullable', 'string', 'max:255'],
            'products.*.jobId' => ['nullable', 'string', 'max:255'],
            'products.*.productCode' => ['nullable', 'string', 'max:255'],
            'products.*.name' => ['nullable', 'string', 'max:255'],
            'products.*.price' => ['nullable', 'string', 'max:255'],
            'products.*.priceText' => ['nullable', 'string', 'max:255'],
            'products.*.priceValue' => ['nullable', 'numeric', 'min:0'],
            'products.*.currency' => ['nullable', 'string', 'max:20'],
            'products.*.url' => ['nullable', 'string', 'max:2048'],
            'products.*.link' => ['nullable', 'string', 'max:2048'],
            'products.*.sourceUrl' => ['nullable', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $source = $validated['source'];
        $products = $validated['products'];
        $now = now();
        $pushedAt = $this->parseDate($source['pushedAt'] ?? null) ?? $now;
        $externalJobId = $this->limit($source['jobId'] ?? '', 255) ?? '';

        $result = DB::transaction(function () use ($source, $products, $now, $pushedAt, $externalJobId): array {
            $jobData = [
                'app' => $this->limit($source['app'] ?? null, 100),
                'start_url' => $this->limit($source['startUrl'] ?? null, 2048),
                'mode' => $this->limit($source['mode'] ?? null, 50),
                'product_count' => max(0, (int) ($source['productCount'] ?? count($products))),
                'batch_index' => isset($source['batch']['index']) ? max(1, (int) $source['batch']['index']) : null,
                'batch_total' => isset($source['batch']['total']) ? max(1, (int) $source['batch']['total']) : null,
                'last_batch_size' => isset($source['batch']['size']) ? max(0, (int) $source['batch']['size']) : count($products),
                'last_pushed_at' => $pushedAt,
                'raw_source' => $this->json($source),
                'updated_at' => $now,
            ];

            $job = DB::table('scanner_import_jobs')
                ->where('external_job_id', $externalJobId)
                ->first(['id']);

            if ($job) {
                $jobId = (int) $job->id;
                DB::table('scanner_import_jobs')->where('id', $jobId)->update($jobData);
            } else {
                $jobId = (int) DB::table('scanner_import_jobs')->insertGetId([
                    'external_job_id' => $externalJobId,
                    ...$jobData,
                    'created_at' => $now,
                ]);
            }

            $inserted = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($products as $product) {
                $url = $this->limit($product['url'] ?? $product['link'] ?? null, 2048);
                if (! $url) {
                    $skipped++;
                    continue;
                }

                $sourceUrl = $this->limit($product['sourceUrl'] ?? $source['startUrl'] ?? null, 2048);
                $priceText = $this->limit($product['priceText'] ?? $product['price'] ?? null, 255);
                $dedupeHash = sha1(mb_strtolower((string) $sourceUrl).'|'.mb_strtolower($url));
                $exists = DB::table('scanner_import_products')
                    ->where('scanner_import_job_id', $jobId)
                    ->where('dedupe_hash', $dedupeHash)
                    ->exists();

                DB::table('scanner_import_products')->updateOrInsert(
                    [
                        'scanner_import_job_id' => $jobId,
                        'dedupe_hash' => $dedupeHash,
                    ],
                    [
                        'external_id' => $this->limit($product['externalId'] ?? null, 255),
                        'external_job_id' => $this->limit($product['jobId'] ?? $externalJobId, 255),
                        'product_code' => $this->limit($product['productCode'] ?? null, 255),
                        'name' => $this->limit($product['name'] ?? null, 255),
                        'price_text' => $priceText,
                        'price_value' => $this->parsePriceValue($product['priceValue'] ?? null, $priceText),
                        'currency' => $this->limit($product['currency'] ?? null, 20),
                        'url' => $url,
                        'link' => $this->limit($product['link'] ?? $url, 2048),
                        'source_url' => $sourceUrl,
                        'url_hash' => sha1(mb_strtolower($url)),
                        'source_url_hash' => $sourceUrl ? sha1(mb_strtolower($sourceUrl)) : null,
                        'imported_at' => $now,
                        'raw_payload' => $this->json($product),
                        'updated_at' => $now,
                        'created_at' => $exists ? DB::raw('created_at') : $now,
                    ]
                );

                if ($exists) {
                    $updated++;
                } else {
                    $inserted++;
                }
            }

            $storedCount = DB::table('scanner_import_products')
                ->where('scanner_import_job_id', $jobId)
                ->count();
            $pricedCount = DB::table('scanner_import_products')
                ->where('scanner_import_job_id', $jobId)
                ->where('price_value', '>', 0)
                ->count();

            DB::table('scanner_import_jobs')
                ->where('id', $jobId)
                ->update([
                    'imported_product_count' => (int) $storedCount,
                    'priced_product_count' => (int) $pricedCount,
                    'updated_at' => $now,
                ]);

            return [
                'job_id' => $externalJobId,
                'received' => count($products),
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'stored' => (int) $storedCount,
            ];
        });

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
    }

    private function readApiKey(Request $request): string
    {
        $bearer = trim((string) $request->bearerToken());
        if ($bearer !== '') {
            return $bearer;
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return trim((string) $request->header('x-api-key', ''));
    }

    private function limit(mixed $value, int $length): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $length);
    }

    private function parseDate(mixed $value): mixed
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parsePriceValue(mixed $value, ?string $priceText): ?int
    {
        if (is_numeric($value) && (float) $value > 0) {
            return (int) round((float) $value);
        }

        $digits = preg_replace('/[^\d]/', '', (string) ($priceText ?? '')) ?? '';

        return $digits !== '' ? (int) $digits : null;
    }

    private function json(mixed $value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : null;
    }
}
