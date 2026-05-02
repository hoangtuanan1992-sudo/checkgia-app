<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Services\ProductCodeExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardCompareMatchController extends Controller
{
    private const MIN_AI_CONFIDENCE = 0.72;

    public function run(Request $request): RedirectResponse
    {
        if ($request->user()->isViewer()) {
            abort(403);
        }

        $validated = $request->validate([
            'mode' => ['required', 'in:all,empty'],
        ]);

        if (! Schema::hasTable('scanner_import_jobs') || ! Schema::hasTable('scanner_import_products')) {
            return back()->with('status', 'Chưa có bảng dữ liệu scanner. Hãy chạy migration import trước.');
        }

        $ai = $this->resolveAiConfig();
        if (! $ai['ok']) {
            return back()->with('status', $ai['message']);
        }

        try {
            @set_time_limit(240);
        } catch (\Throwable) {
        }

        $userId = $request->user()->effectiveUserId();
        $mode = (string) $validated['mode'];

        $sites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        if ($sites->isEmpty()) {
            return back()->with('status', 'Chưa có cột đối thủ để so khớp.');
        }

        $stats = [
            'checked' => 0,
            'matched' => 0,
            'skipped_existing' => 0,
            'no_candidates' => 0,
            'no_match' => 0,
            'errors' => 0,
        ];
        $errorSamples = [];

        $products = Product::query()
            ->where('user_id', $userId)
            ->with('competitors')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $map = $product->competitors->keyBy('competitor_site_id');

            foreach ($sites as $site) {
                $existing = $map->get($site->id);
                if ($mode === 'empty' && $existing && trim((string) $existing->url) !== '') {
                    $stats['skipped_existing']++;
                    continue;
                }

                $stats['checked']++;
                $candidates = $this->findCandidates($product, $site);
                if ($candidates === []) {
                    $stats['no_candidates']++;
                    continue;
                }

                try {
                    $match = $this->askAiForMatch($ai['config'], $product, $site, $candidates);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    if (count($errorSamples) < 3) {
                        $errorSamples[] = $e->getMessage();
                    }
                    if ($stats['errors'] >= 3) {
                        break 2;
                    }
                    continue;
                }

                $candidate = $this->matchedCandidate($candidates, $match);
                if (! $candidate || (float) ($match['confidence'] ?? 0) < self::MIN_AI_CONFIDENCE) {
                    $stats['no_match']++;
                    continue;
                }

                $this->saveMatch($product, $site, $candidate);
                $stats['matched']++;
            }
        }

        $message = 'Đã so khớp '.$stats['checked'].' ô, điền được '.$stats['matched'].' link.';
        if ($mode === 'empty') {
            $message .= ' Đã bỏ qua '.$stats['skipped_existing'].' ô đã có link.';
        }
        if ($stats['no_candidates'] > 0) {
            $message .= ' '.$stats['no_candidates'].' ô chưa có ứng viên scanner.';
        }
        if ($stats['errors'] > 0) {
            $message .= ' Có '.$stats['errors'].' lỗi AI.';
            if ($errorSamples !== []) {
                $message .= ' Lỗi đầu: '.Str::limit($errorSamples[0], 160);
            }
        }

        return back()->with('status', $message);
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
