<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\CompareMatchRun;
use App\Models\CompareMatchRunItem;
use App\Models\Product;
use App\Services\ProductCodeExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardCompareMatchController extends Controller
{
    private const MIN_AI_CONFIDENCE = 0.72;

    public function run(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->user()->isViewer()) {
            abort(403);
        }

        $validated = $request->validate([
            'mode' => ['required', 'in:all,empty'],
        ]);

        if (! Schema::hasTable('scanner_import_jobs') || ! Schema::hasTable('scanner_import_products')) {
            return $this->startError($request, 'Chưa có bảng dữ liệu scanner. Hãy chạy migration import trước.');
        }

        if (! Schema::hasTable('compare_match_runs') || ! Schema::hasTable('compare_match_run_items')) {
            return $this->startError($request, 'Chưa có bảng tiến trình so khớp. Hãy chạy migration mới trên hosting.');
        }

        $ai = $this->resolveAiConfig();
        if (! $ai['ok']) {
            return $this->startError($request, $ai['message']);
        }

        $userId = $request->user()->effectiveUserId();
        $mode = (string) $validated['mode'];

        $sites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        if ($sites->isEmpty()) {
            return $this->startError($request, 'Chưa có cột đối thủ để so khớp.');
        }

        $run = null;
        $totalCells = 0;
        $totalProducts = 0;
        $skippedExisting = 0;

        DB::transaction(function () use ($userId, $mode, $sites, &$run, &$totalCells, &$totalProducts, &$skippedExisting) {
            $run = CompareMatchRun::query()->create([
                'user_id' => $userId,
                'mode' => $mode,
                'status' => 'queued',
                'message' => 'Đang chuẩn bị danh sách so khớp.',
            ]);

            Product::query()
                ->where('user_id', $userId)
                ->with('competitors:id,product_id,competitor_site_id,url')
                ->orderBy('id')
                ->chunkById(300, function ($products) use ($sites, $mode, $run, &$totalCells, &$totalProducts, &$skippedExisting) {
                    $rows = [];

                    foreach ($products as $product) {
                        $map = $product->competitors->keyBy('competitor_site_id');
                        $productCells = 0;

                        foreach ($sites as $site) {
                            $existing = $map->get($site->id);
                            if ($mode === 'empty' && $existing && trim((string) $existing->url) !== '') {
                                $skippedExisting++;
                                continue;
                            }

                            $rows[] = [
                                'compare_match_run_id' => $run->id,
                                'product_id' => $product->id,
                                'competitor_site_id' => $site->id,
                                'status' => 'pending',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                            $productCells++;
                        }

                        if ($productCells > 0) {
                            $totalProducts++;
                            $totalCells += $productCells;
                        }
                    }

                    foreach (array_chunk($rows, 1000) as $chunk) {
                        CompareMatchRunItem::query()->insert($chunk);
                    }
                });

            $run->update([
                'total_products' => $totalProducts,
                'total_cells' => $totalCells,
                'skipped_existing_count' => $skippedExisting,
                'status' => $totalCells > 0 ? 'queued' : 'done',
                'message' => $totalCells > 0
                    ? 'Đã sẵn sàng so khớp.'
                    : ($mode === 'empty' ? 'Không còn ô trống cần so khớp.' : 'Chưa có sản phẩm cần so khớp.'),
                'finished_at' => $totalCells > 0 ? null : now(),
            ]);
        });

        $run->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'run' => $this->progressPayload($run),
            ]);
        }

        return back()->with('status', 'Đã tạo tiến trình so khớp. Vui lòng chạy trong popup để xem tiến độ.');
    }

    public function tick(Request $request, CompareMatchRun $compareMatchRun): JsonResponse
    {
        if ($request->user()->isViewer()) {
            abort(403);
        }

        $userId = $request->user()->effectiveUserId();
        if ((int) $compareMatchRun->user_id !== (int) $userId) {
            abort(404);
        }

        if (in_array($compareMatchRun->status, ['done', 'failed'], true)) {
            return response()->json([
                'ok' => true,
                'run' => $this->progressPayload($compareMatchRun->refresh()),
            ]);
        }

        $ai = $this->resolveAiConfig();
        if (! $ai['ok']) {
            $compareMatchRun->update([
                'status' => 'failed',
                'message' => $ai['message'],
                'finished_at' => now(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $ai['message'],
                'run' => $this->progressPayload($compareMatchRun->refresh()),
            ], 422);
        }

        try {
            @set_time_limit(90);
        } catch (\Throwable) {
        }

        $compareMatchRun->update([
            'status' => 'running',
            'started_at' => $compareMatchRun->started_at ?: now(),
        ]);

        $item = CompareMatchRunItem::query()
            ->where('compare_match_run_id', $compareMatchRun->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->first();

        if (! $item) {
            $this->finishRunIfComplete($compareMatchRun);

            return response()->json([
                'ok' => true,
                'run' => $this->progressPayload($compareMatchRun->refresh()),
            ]);
        }

        $this->processItem($compareMatchRun, $item, $ai['config']);

        $this->finishRunIfComplete($compareMatchRun);

        return response()->json([
            'ok' => true,
            'run' => $this->progressPayload($compareMatchRun->refresh()),
        ]);
    }

    private function startError(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 422);
        }

        return back()->with('status', $message);
    }

    private function processItem(CompareMatchRun $run, CompareMatchRunItem $item, array $ai): void
    {
        $product = Product::query()
            ->where('user_id', $run->user_id)
            ->find($item->product_id);
        $site = CompetitorSite::query()
            ->where('user_id', $run->user_id)
            ->find($item->competitor_site_id);

        if (! $product || ! $site) {
            $this->markItemProcessed($run, $item, 'error', 'Sản phẩm hoặc cột đối thủ đã bị xoá.');

            return;
        }

        $run->update([
            'current_product_name' => Str::limit((string) $product->name, 255, ''),
            'message' => 'Đang so khớp: '.$product->name,
        ]);

        if ($run->mode === 'empty') {
            $existing = Competitor::query()
                ->where('product_id', $product->id)
                ->where('competitor_site_id', $site->id)
                ->first();
            if ($existing && trim((string) $existing->url) !== '') {
                $this->markItemProcessed($run, $item, 'skipped_existing', 'Ô đã có link.');

                return;
            }
        }

        $candidates = $this->findCandidates($product, $site);
        if ($candidates === []) {
            $this->markItemProcessed($run, $item, 'no_candidates', 'Không có ứng viên scanner.');

            return;
        }

        try {
            $match = $this->askAiForMatch($ai, $product, $site, $candidates);
            $candidate = $this->matchedCandidate($candidates, $match);

            if (! $candidate || (float) ($match['confidence'] ?? 0) < self::MIN_AI_CONFIDENCE) {
                $this->markItemProcessed($run, $item, 'no_match', 'AI chưa xác nhận được sản phẩm trùng.');

                return;
            }

            $this->saveMatch($product, $site, $candidate);
            $this->markItemProcessed($run, $item, 'matched', 'Đã điền link.', (string) $candidate['url']);
        } catch (\Throwable $e) {
            $this->markItemProcessed($run, $item, 'error', $e->getMessage());
        }
    }

    private function markItemProcessed(CompareMatchRun $run, CompareMatchRunItem $item, string $status, string $message, ?string $matchedUrl = null): void
    {
        DB::transaction(function () use ($run, $item, $status, $message, $matchedUrl) {
            $item->update([
                'status' => $status,
                'matched_url' => $matchedUrl,
                'message' => Str::limit($message, 1000, ''),
                'processed_at' => now(),
            ]);

            $increments = [
                'processed_cells' => DB::raw('processed_cells + 1'),
            ];

            if ($status === 'matched') {
                $increments['matched_count'] = DB::raw('matched_count + 1');
            } elseif ($status === 'skipped_existing') {
                $increments['skipped_existing_count'] = DB::raw('skipped_existing_count + 1');
            } elseif ($status === 'no_candidates') {
                $increments['no_candidates_count'] = DB::raw('no_candidates_count + 1');
            } elseif ($status === 'no_match') {
                $increments['no_match_count'] = DB::raw('no_match_count + 1');
            } elseif ($status === 'error') {
                $increments['error_count'] = DB::raw('error_count + 1');
                $samples = $run->error_samples ?: [];
                if (count($samples) < 3) {
                    $samples[] = Str::limit($message, 300, '');
                    $increments['error_samples'] = json_encode($samples, JSON_UNESCAPED_UNICODE);
                }
            }

            $hasPendingForProduct = CompareMatchRunItem::query()
                ->where('compare_match_run_id', $run->id)
                ->where('product_id', $item->product_id)
                ->where('status', 'pending')
                ->exists();

            if (! $hasPendingForProduct) {
                $increments['processed_products'] = DB::raw('processed_products + 1');
            }

            CompareMatchRun::query()
                ->whereKey($run->id)
                ->update($increments + [
                    'message' => $message,
                    'updated_at' => now(),
                ]);
        });
    }

    private function finishRunIfComplete(CompareMatchRun $run): void
    {
        $pending = CompareMatchRunItem::query()
            ->where('compare_match_run_id', $run->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return;
        }

        $run->refresh();
        $message = 'Hoàn tất: đã xử lý '.$run->processed_cells.'/'.$run->total_cells.' ô, điền được '.$run->matched_count.' link.';
        if ((int) $run->error_count > 0) {
            $message .= ' Có '.$run->error_count.' lỗi.';
        }

        $run->update([
            'status' => 'done',
            'message' => $message,
            'current_product_name' => null,
            'finished_at' => now(),
        ]);
    }

    private function progressPayload(CompareMatchRun $run): array
    {
        $totalCells = max(0, (int) $run->total_cells);
        $processedCells = max(0, (int) $run->processed_cells);
        $totalProducts = max(0, (int) $run->total_products);
        $processedProducts = max(0, (int) $run->processed_products);

        return [
            'id' => (int) $run->id,
            'mode' => (string) $run->mode,
            'status' => (string) $run->status,
            'totalProducts' => $totalProducts,
            'processedProducts' => min($processedProducts, $totalProducts),
            'remainingProducts' => max(0, $totalProducts - $processedProducts),
            'totalCells' => $totalCells,
            'processedCells' => min($processedCells, $totalCells),
            'remainingCells' => max(0, $totalCells - $processedCells),
            'matched' => (int) $run->matched_count,
            'skippedExisting' => (int) $run->skipped_existing_count,
            'noCandidates' => (int) $run->no_candidates_count,
            'noMatch' => (int) $run->no_match_count,
            'errors' => (int) $run->error_count,
            'currentProductName' => (string) ($run->current_product_name ?? ''),
            'message' => (string) ($run->message ?? ''),
            'percent' => $totalCells > 0 ? (int) floor(($processedCells / $totalCells) * 100) : 100,
        ];
    }

    private function resolveAiConfig(): array
    {
        $setting = AppSetting::current();
        if (! $setting) {
            return [
                'ok' => false,
                'message' => 'Chưa cấu hình API AI trong Admin.',
            ];
        }

        $provider = strtolower(trim((string) ($setting->ai_provider ?? '')));
        $map = [
            'grok' => ['key' => 'grok_api_key', 'model' => 'grok_model', 'label' => 'Grok'],
            'gemini' => ['key' => 'gemini_api_key', 'model' => 'gemini_model', 'label' => 'Gemini'],
            'chatgpt' => ['key' => 'chatgpt_api_key', 'model' => 'chatgpt_model', 'label' => 'ChatGPT'],
        ];

        if (! isset($map[$provider])) {
            return [
                'ok' => false,
                'message' => 'Hãy chọn nhà cung cấp AI trong Admin > Cài đặt tổng trước khi so khớp.',
            ];
        }

        $key = trim((string) ($setting->{$map[$provider]['key']} ?? ''));
        $model = trim((string) ($setting->{$map[$provider]['model']} ?? ''));

        if ($key === '' || $model === '') {
            return [
                'ok' => false,
                'message' => 'Hãy nhập API key và chọn mô hình '.$map[$provider]['label'].' trong Admin > Cài đặt tổng.',
            ];
        }

        return [
            'ok' => true,
            'config' => [
                'provider' => $provider,
                'api_key' => $key,
                'model' => $model,
            ],
        ];
    }

    private function findCandidates(Product $product, CompetitorSite $site): array
    {
        $domain = $site->domain ?: CompetitorSite::normalizedDomainFromUserInput($site->name);
        $domain = $domain ? strtolower((string) $domain) : '';
        if ($domain === '') {
            return [];
        }

        $terms = $this->searchTerms($product);
        $rows = $this->candidateQuery($domain, $terms)->limit(500)->get();

        if ($rows->count() < 20 && $terms !== []) {
            $fallbackRows = $this->candidateQuery($domain, [])->limit(500)->get();
            $rows = $rows->merge($fallbackRows)->unique('id')->values();
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $ownTokens = $this->tokens((string) $product->name);
        $ownCode = ProductCodeExtractor::best('', (string) $product->name, (string) $product->product_url);
        $ownCodeKey = $this->codeKey($ownCode);
        $ownPrice = (int) $product->price;

        $candidates = [];
        foreach ($rows as $row) {
            $name = (string) ($row->name ?? '');
            $url = (string) (($row->url ?? '') ?: ($row->link ?? ''));
            if ($url === '') {
                continue;
            }

            $candidateCode = ProductCodeExtractor::best(
                (string) ($row->product_code ?? ''),
                $name,
                $url,
                (string) ($row->source_url ?? '')
            );
            $candidateCodeKey = $this->codeKey($candidateCode);
            $candidateTokens = $this->tokens($name);
            $overlap = count(array_intersect($ownTokens, $candidateTokens));
            $ratio = count($ownTokens) > 0 ? $overlap / max(1, count($ownTokens)) : 0;
            $candidatePrice = (int) ($row->price_value ?? 0);
            $score = (int) round(($overlap * 18) + ($ratio * 120));

            if ($ownCodeKey !== '' && $candidateCodeKey !== '') {
                if ($ownCodeKey === $candidateCodeKey) {
                    $score += 1200;
                } elseif (str_contains($candidateCodeKey, $ownCodeKey) || str_contains($ownCodeKey, $candidateCodeKey)) {
                    $score += 360;
                }
            }

            if ($ownPrice > 0 && $candidatePrice > 0) {
                $diffRatio = abs($candidatePrice - $ownPrice) / max($ownPrice, $candidatePrice);
                if ($diffRatio <= 0.05) {
                    $score += 80;
                } elseif ($diffRatio <= 0.12) {
                    $score += 40;
                }
            }

            $key = $this->comparableUrl($url);
            if ($key === '') {
                continue;
            }

            $candidate = [
                'id' => (int) $row->id,
                'name' => $name,
                'code' => $candidateCode,
                'priceText' => (string) ($row->price_text ?? ''),
                'priceValue' => $candidatePrice > 0 ? $candidatePrice : null,
                'url' => $url,
                'sourceUrl' => (string) ($row->source_url ?? ''),
                'score' => $score,
                'updatedAt' => (string) ($row->updated_at ?? ''),
            ];

            $previous = $candidates[$key] ?? null;
            if (! $previous || $score > (int) $previous['score']) {
                $candidates[$key] = $candidate;
            }
        }

        usort($candidates, fn (array $a, array $b): int => ((int) $b['score'] <=> (int) $a['score'])
            ?: strcmp((string) $b['updatedAt'], (string) $a['updatedAt']));

        $candidates = array_slice(array_values($candidates), 0, 100);
        foreach ($candidates as $i => $candidate) {
            $candidates[$i]['candidateId'] = $i + 1;
        }

        return $candidates;
    }

    private function candidateQuery(string $domain, array $terms): \Illuminate\Database\Query\Builder
    {
        $domainLike = '%'.$this->escapeLike($domain).'%';

        $query = DB::table('scanner_import_products as p')
            ->join('scanner_import_jobs as j', 'j.id', '=', 'p.scanner_import_job_id')
            ->select([
                'p.id',
                'p.product_code',
                'p.name',
                'p.price_text',
                'p.price_value',
                'p.url',
                'p.link',
                'p.source_url',
                'p.updated_at',
                'j.start_url',
            ])
            ->where(function ($q) use ($domainLike) {
                $q->where('p.url', 'like', $domainLike)
                    ->orWhere('p.link', 'like', $domainLike)
                    ->orWhere('p.source_url', 'like', $domainLike)
                    ->orWhere('j.start_url', 'like', $domainLike);
            });

        if ($terms !== []) {
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%'.$this->escapeLike($term).'%';
                    $q->orWhere('p.name', 'like', $like)
                        ->orWhere('p.product_code', 'like', $like)
                        ->orWhere('p.url', 'like', $like);
                }
            });
        }

        return $query
            ->orderByDesc('p.updated_at')
            ->orderByDesc('p.id');
    }

    private function askAiForMatch(array $ai, Product $product, CompetitorSite $site, array $candidates): array
    {
        $prompt = $this->matchPrompt($product, $site, $candidates);
        $provider = (string) $ai['provider'];

        if ($provider === 'gemini') {
            $model = (string) $ai['model'];
            $model = str_starts_with($model, 'models/') ? $model : 'models/'.$model;
            $response = Http::acceptJson()
                ->timeout(45)
                ->post('https://generativelanguage.googleapis.com/v1beta/'.$model.':generateContent?key='.rawurlencode((string) $ai['api_key']), [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } else {
            $url = $provider === 'grok'
                ? 'https://api.x.ai/v1/chat/completions'
                : 'https://api.openai.com/v1/chat/completions';

            $payload = [
                'model' => (string) $ai['model'],
                'temperature' => 0,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You match ecommerce products. Return only valid JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ];

            if ($provider === 'chatgpt') {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $response = Http::withToken((string) $ai['api_key'])
                ->acceptJson()
                ->timeout(45)
                ->post($url, $payload);
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->summarizeAiApiError($response->json(), $response->body(), $response->status()));
        }

        $json = $response->json();
        if ($provider === 'gemini') {
            $content = (string) data_get($json, 'candidates.0.content.parts.0.text', '');
        } else {
            $content = (string) data_get($json, 'choices.0.message.content', '');
        }

        return $this->parseAiMatch($content);
    }

    private function matchPrompt(Product $product, CompetitorSite $site, array $candidates): string
    {
        $ownCode = ProductCodeExtractor::best('', (string) $product->name, (string) $product->product_url);
        $payload = [
            'instruction' => 'Chọn đúng 1 ứng viên là cùng một sản phẩm với sản phẩm của tôi. Phải cùng mã model/biến thể/dung lượng/màu hoặc thông số chính. Nếu không chắc chắn thì trả null. Không chọn sản phẩm khác phiên bản.',
            'outputJson' => [
                'candidateId' => 'number|null',
                'matchUrl' => 'string|null',
                'confidence' => 'number 0..1',
                'reason' => 'short string',
            ],
            'myProduct' => [
                'name' => (string) $product->name,
                'code' => $ownCode,
                'priceValue' => (int) $product->price,
                'url' => (string) $product->product_url,
            ],
            'competitorSite' => [
                'name' => (string) $site->name,
                'domain' => (string) ($site->domain ?? ''),
            ],
            'candidates' => array_map(fn (array $candidate): array => [
                'candidateId' => (int) $candidate['candidateId'],
                'name' => (string) $candidate['name'],
                'code' => (string) $candidate['code'],
                'priceValue' => $candidate['priceValue'],
                'priceText' => (string) $candidate['priceText'],
                'url' => (string) $candidate['url'],
            ], $candidates),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function parseAiMatch(string $content): array
    {
        $content = trim($content);
        $decoded = json_decode($content, true);

        if (! is_array($decoded) && preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
        }

        if (! is_array($decoded)) {
            return [
                'candidateId' => null,
                'matchUrl' => null,
                'confidence' => 0.0,
                'reason' => 'AI did not return JSON.',
            ];
        }

        $confidence = $decoded['confidence'] ?? $decoded['score'] ?? 0;
        $confidence = is_numeric($confidence) ? (float) $confidence : 0.0;
        if ($confidence > 1) {
            $confidence = $confidence / 100;
        }

        return [
            'candidateId' => isset($decoded['candidateId']) && is_numeric($decoded['candidateId']) ? (int) $decoded['candidateId'] : null,
            'matchUrl' => is_string($decoded['matchUrl'] ?? null) ? trim((string) $decoded['matchUrl']) : null,
            'confidence' => max(0.0, min(1.0, $confidence)),
            'reason' => is_string($decoded['reason'] ?? null) ? (string) $decoded['reason'] : '',
        ];
    }

    private function matchedCandidate(array $candidates, array $match): ?array
    {
        $candidateId = $match['candidateId'] ?? null;
        if (is_int($candidateId) && $candidateId > 0) {
            foreach ($candidates as $candidate) {
                if ((int) $candidate['candidateId'] === $candidateId) {
                    return $candidate;
                }
            }
        }

        $url = $this->comparableUrl((string) ($match['matchUrl'] ?? ''));
        if ($url === '') {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($this->comparableUrl((string) $candidate['url']) === $url) {
                return $candidate;
            }
        }

        return null;
    }

    private function saveMatch(Product $product, CompetitorSite $site, array $candidate): void
    {
        DB::transaction(function () use ($product, $site, $candidate) {
            $competitor = Competitor::query()->firstOrNew([
                'product_id' => $product->id,
                'competitor_site_id' => $site->id,
            ]);

            $oldUrl = (string) ($competitor->url ?? '');
            $competitor->name = $site->name ?: (($site->domain ?: 'Đối thủ'));
            $competitor->url = (string) $candidate['url'];
            $competitor->save();

            $price = $candidate['priceValue'] ?? null;
            if (! is_int($price) || $price <= 0) {
                return;
            }

            $latest = $competitor->prices()->latest('fetched_at')->first();
            if ($latest && (int) $latest->price === $price && $oldUrl === (string) $candidate['url']) {
                return;
            }

            CompetitorPrice::query()->create([
                'competitor_id' => $competitor->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);
        });
    }

    private function searchTerms(Product $product): array
    {
        $terms = [];
        $code = ProductCodeExtractor::best('', (string) $product->name, (string) $product->product_url);
        if ($code !== '') {
            $terms[] = $code;
            $key = $this->codeKey($code);
            if ($key !== '') {
                $terms[] = $key;
            }
        }

        foreach ($this->tokens((string) $product->name) as $token) {
            $terms[] = $token;
        }

        $terms = array_values(array_unique(array_filter($terms, fn (string $term): bool => trim($term) !== '')));

        return array_slice($terms, 0, 14);
    }

    private function tokens(string $value): array
    {
        $text = Str::ascii(mb_strtolower($value, 'UTF-8'));
        $parts = preg_split('/[^a-z0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = array_flip([
            'san', 'pham', 'gia', 'ban', 'chinh', 'hang', 'new', 'full', 'hd', 'fhd', 'uhd',
            'the', 'and', 'for', 'with', 'den', 'trang', 'xam', 'bac', 'vang', 'xanh',
            'may', 'tu', 'lanh', 'inverter', 'lit', 'inch', 'man', 'hinh',
        ]);

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part) < 3 || isset($stopWords[$part])) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    private function codeKey(string $value): string
    {
        $value = ProductCodeExtractor::sanitize($value);

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    private function comparableUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $url = preg_replace('/#.*$/', '', $url) ?? $url;

        return rtrim(mb_strtolower($url, 'UTF-8'), '/');
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, "\\%_");
    }

    private function summarizeAiApiError(mixed $json, string $body, int $status): string
    {
        if (is_array($json)) {
            $message = $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        $body = trim(strip_tags($body));
        if ($body !== '') {
            return 'HTTP '.$status.' - '.Str::limit($body, 300);
        }

        return 'HTTP '.$status;
    }
}
