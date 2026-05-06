<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteTemplate;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ScrapeAgentJob;
use App\Models\UserScrapeXpath;
use App\Models\UserScrapeSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ScrapeAgentJobService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function lease(string $agentId, int $limit, array $capabilities = []): array
    {
        $limit = max(1, min(20, $limit));
        $this->expireLeases();
        $this->refreshDueJobs(max(20, $limit * 10));

        return DB::transaction(function () use ($agentId, $limit): array {
            $now = now();
            $leaseSeconds = max(60, (int) config('services.checkgia_agent.lease_seconds', 900));

            $jobs = ScrapeAgentJob::query()
                ->where('status', 'pending')
                ->where(function ($q) use ($now) {
                    $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
                })
                ->orderBy('priority')
                ->orderBy('attempts')
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $leased = [];
            foreach ($jobs as $job) {
                $job->forceFill([
                    'status' => 'leased',
                    'leased_by_agent_id' => $agentId,
                    'lease_token' => Str::random(48),
                    'leased_at' => $now,
                    'lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                    'attempts' => ((int) $job->attempts) + 1,
                    'finished_at' => null,
                ])->save();

                $leased[] = $this->toAgentPayload($job);
            }

            return $leased;
        });
    }

    public function recordResult(array $payload): array
    {
        $jobId = trim((string) ($payload['jobId'] ?? ''));
        $job = ScrapeAgentJob::query()->where('job_uuid', $jobId)->first();

        if (! $job) {
            return [
                'ok' => false,
                'status' => 404,
                'error' => 'Job not found.',
            ];
        }

        DB::transaction(function () use ($job, $payload): void {
            $job = ScrapeAgentJob::query()
                ->whereKey($job->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = (string) ($payload['status'] ?? 'failed');
            $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
            $fetchedAt = $this->fetchedAt($result);

            if ($job->type === 'test') {
                // Test jobs are for admin verification only. Keep the evidence in result_payload
                // and never update product or competitor data.
            } elseif ($job->competitor_id || ! empty($payload['competitorId'])) {
                $this->applyCompetitorResult($job, $payload, $result, $status, $fetchedAt);
            } else {
                $this->applyProductResult($job, $payload, $result, $status, $fetchedAt);
            }

            $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];
            $jobUpdates = [
                'status' => $status === 'failed' ? 'failed' : 'done',
                'leased_by_agent_id' => null,
                'lease_token' => null,
                'leased_at' => null,
                'lease_expires_at' => null,
                'finished_at' => now(),
                'last_error_code' => isset($error['code']) ? mb_substr((string) $error['code'], 0, 80) : null,
                'last_error' => isset($error['message']) ? mb_substr((string) $error['message'], 0, 4000) : null,
                'result_payload' => $payload,
            ];
            if (Schema::hasColumn('scrape_agent_jobs', 'completed_by_agent_id')) {
                $jobUpdates['completed_by_agent_id'] = (string) ($payload['agentId'] ?? '');
            }
            $job->forceFill($jobUpdates)->save();
        });

        return [
            'ok' => true,
            'status' => 200,
        ];
    }

    private function expireLeases(): void
    {
        ScrapeAgentJob::query()
            ->where('status', 'leased')
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', now())
            ->update([
                'status' => 'pending',
                'leased_by_agent_id' => null,
                'lease_token' => null,
                'leased_at' => null,
                'lease_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array{deleted:int, deleted_hosting:int, created:int, target:int}
     */
    public function rebuildPendingQueue(int $targetQueueSize = 1000): array
    {
        $targetQueueSize = max(20, min(5000, $targetQueueSize));
        $this->expireLeases();

        $deletedHosting = $this->deletePendingHostingScrapeJobs();

        $deleted = ScrapeAgentJob::query()
            ->where('status', 'pending')
            ->whereIn('type', ['product', 'competitor'])
            ->delete();

        return [
            'deleted' => (int) $deleted,
            'deleted_hosting' => $deletedHosting,
            'created' => $this->refreshDueJobs($targetQueueSize, false),
            'target' => $targetQueueSize,
        ];
    }

    private function deletePendingHostingScrapeJobs(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return (int) DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('payload', 'like', '%ScrapeProductPrices%')
            ->delete();
    }

    private function refreshDueJobs(int $targetQueueSize, bool $respectReadyCount = true): int
    {
        $ready = ScrapeAgentJob::query()
            ->where('status', 'pending')
            ->whereIn('type', ['product', 'competitor'])
            ->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->count();

        if ($respectReadyCount && $ready >= $targetQueueSize) {
            return 0;
        }

        $created = 0;
        $now = now('Asia/Ho_Chi_Minh');
        $hasScheduleTimes = Schema::hasColumn('user_scrape_settings', 'scrape_schedule_times');
        $hasScrapePriority = Schema::hasColumn('user_scrape_settings', 'scrape_priority');
        $settings = UserScrapeSetting::query()->get()->keyBy('user_id');
        $userIds = Product::query()
            ->whereNotNull('product_url')
            ->distinct()
            ->pluck('user_id')
            ->all();

        usort($userIds, function ($a, $b) use ($settings, $hasScrapePriority): int {
            $priorityA = $hasScrapePriority ? (int) ($settings->get((int) $a)->scrape_priority ?? 50) : 50;
            $priorityB = $hasScrapePriority ? (int) ($settings->get((int) $b)->scrape_priority ?? 50) : 50;

            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return (int) $a <=> (int) $b;
        });

        foreach ($userIds as $userId) {
            if ($created >= $targetQueueSize) {
                break;
            }

            $setting = $settings->get((int) $userId) ?? new UserScrapeSetting([
                'user_id' => (int) $userId,
                'scrape_interval_minutes' => 10,
                'scrape_schedule_times' => '',
                'scrape_priority' => 50,
            ]);
            $scrapePriority = $this->scrapePriority($setting);
            $scheduledHours = $hasScheduleTimes ? $setting->scheduledHours() : [];

            if ($scheduledHours !== []) {
                if ((int) $now->minute !== 0 || ! in_array((int) $now->hour, $scheduledHours, true)) {
                    continue;
                }

                $cutoff = $now->copy()->startOfHour();
            } else {
                $interval = $hasScheduleTimes ? 10 : max(5, (int) ($setting->scrape_interval_minutes ?: 10));
                $cutoff = $now->copy()->subMinutes($interval);
            }

            $remaining = max(1, $targetQueueSize - $created);
            $products = Product::query()
                ->with(['competitors.competitorSite'])
                ->where('user_id', (int) $userId)
                ->whereNotNull('product_url')
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_scraped_at')->orWhere('last_scraped_at', '<=', $cutoff);
                })
                ->orderByRaw('last_scraped_at is null desc')
                ->orderBy('last_scraped_at')
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            foreach ($products as $product) {
                if ($created >= $targetQueueSize) {
                    break;
                }

                if ($this->ensureProductJob($product, $scrapePriority)) {
                    $created++;
                }

                foreach ($product->competitors as $competitor) {
                    if ($this->ensureCompetitorJob($competitor, $scrapePriority)) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    private function ensureProductJob(Product $product, int $scrapePriority): bool
    {
        $url = trim((string) $product->product_url);
        if ($url === '') {
            return false;
        }

        return $this->ensureJob('product:'.$product->id, [
            'type' => 'product',
            'product_id' => (int) $product->id,
            'competitor_id' => null,
            'competitor_site_id' => null,
            'url' => $url,
            'domain' => CompetitorSite::normalizedDomainFromUrl($url),
            'variant_key' => null,
            'variant_name' => null,
            'priority' => $scrapePriority,
        ]);
    }

    private function ensureCompetitorJob(Competitor $competitor, int $scrapePriority): bool
    {
        $url = trim((string) $competitor->url);
        if ($url === '') {
            return false;
        }

        return $this->ensureJob('competitor:'.$competitor->id, [
            'type' => 'competitor',
            'product_id' => (int) $competitor->product_id,
            'competitor_id' => (int) $competitor->id,
            'competitor_site_id' => $competitor->competitor_site_id ? (int) $competitor->competitor_site_id : null,
            'url' => $url,
            'domain' => CompetitorSite::normalizedDomainFromUrl($url),
            'variant_key' => $competitor->variant_key,
            'variant_name' => $competitor->variant_name,
            'priority' => $this->competitorJobPriority($scrapePriority),
        ]);
    }

    private function scrapePriority(UserScrapeSetting $setting): int
    {
        if (! Schema::hasColumn('user_scrape_settings', 'scrape_priority')) {
            return 50;
        }

        return max(1, min(100, (int) ($setting->scrape_priority ?: 50)));
    }

    private function competitorJobPriority(int $scrapePriority): int
    {
        return max(1, min(110, $scrapePriority + 10));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function ensureJob(string $targetKey, array $attributes): bool
    {
        $job = ScrapeAgentJob::query()->where('target_key', $targetKey)->first();
        if ($job && $job->status === 'leased' && $job->lease_expires_at?->isFuture()) {
            return false;
        }
        if ($job && $job->status === 'pending') {
            if ((int) $job->priority !== (int) ($attributes['priority'] ?? $job->priority)) {
                $job->forceFill([
                    'priority' => (int) $attributes['priority'],
                ])->save();
            }

            return false;
        }

        $payload = array_merge($attributes, [
            'target_key' => $targetKey,
            'status' => 'pending',
            'attempts' => 0,
            'leased_by_agent_id' => null,
            'lease_token' => null,
            'leased_at' => null,
            'lease_expires_at' => null,
            'next_run_at' => null,
            'finished_at' => null,
            'last_error_code' => null,
            'last_error' => null,
        ]);
        if (Schema::hasColumn('scrape_agent_jobs', 'completed_by_agent_id')) {
            $payload['completed_by_agent_id'] = null;
        }

        if ($job) {
            $job->forceFill($payload)->save();

            return true;
        }

        ScrapeAgentJob::query()->create(array_merge($payload, [
            'job_uuid' => (string) Str::uuid(),
        ]));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function toAgentPayload(ScrapeAgentJob $job): array
    {
        $scrapeRules = $this->scrapeRulesForJob($job);

        return [
            'jobId' => (string) $job->job_uuid,
            'leaseToken' => (string) $job->lease_token,
            'type' => (string) $job->type,
            'priority' => (int) $job->priority,
            'productId' => $job->product_id ? (int) $job->product_id : null,
            'competitorId' => $job->competitor_id ? (int) $job->competitor_id : null,
            'competitorSiteId' => $job->competitor_site_id ? (int) $job->competitor_site_id : null,
            'website' => (string) ($job->domain ?: ''),
            'domain' => (string) ($job->domain ?: ''),
            'url' => (string) $job->url,
            'variantKey' => $job->variant_key,
            'variantName' => $job->variant_name,
            'needName' => true,
            'needPrice' => true,
            'needVariants' => false,
            'useBrowser' => collect($scrapeRules)->contains(fn (array $rule): bool => (bool) ($rule['useBrowser'] ?? false)),
            'scrapeRules' => $scrapeRules,
            'timeoutSeconds' => (int) config('services.checkgia_agent.job_timeout_seconds', 60),
            'attempt' => (int) $job->attempts,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scrapeRulesForJob(ScrapeAgentJob $job): array
    {
        if ($job->type === 'product' && $job->product_id) {
            $product = Product::query()->find((int) $job->product_id);

            return $product ? $this->productScrapeRules($product, (string) $job->url) : [];
        }

        if ($job->type === 'competitor' && $job->competitor_id) {
            $competitor = Competitor::query()
                ->with(['competitorSite.scrapeXpaths'])
                ->find((int) $job->competitor_id);

            return $competitor ? $this->competitorScrapeRules($competitor, (string) $job->url) : [];
        }

        if ($job->type === 'test') {
            return $this->templateOnlyScrapeRules((string) $job->url);
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productScrapeRules(Product $product, string $url): array
    {
        $rules = [];
        $template = $this->approvedTemplateForUrl($url);
        if ($template) {
            $rule = $this->rulePayload(
                'xpath-template',
                $this->templateXpaths($template, 'name', $template->name_xpath),
                $this->templateXpaths($template, 'price', $template->price_xpath),
                $template->price_regex,
                (int) $template->id,
                $this->templateAdvancedRule($template)
            );
            if ($rule) {
                $rules[] = $rule;
            }
        }

        $settings = UserScrapeSetting::query()->where('user_id', (int) $product->user_id)->first();
        if ($settings) {
            $rule = $this->rulePayload(
                'user-xpath',
                $this->userXpaths((int) $product->user_id, 'name', $settings->own_name_xpath),
                $this->userXpaths((int) $product->user_id, 'price', $settings->own_price_xpath),
                $settings->price_regex
            );
            if ($rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function competitorScrapeRules(Competitor $competitor, string $url): array
    {
        $rules = [];
        $site = $competitor->competitorSite;
        if ($site) {
            $site->loadMissing('scrapeXpaths');
            $rule = $this->rulePayload(
                'site-xpath',
                $this->siteXpaths($site, 'name', $site->name_xpath),
                $this->siteXpaths($site, 'price', $site->price_xpath),
                $site->price_regex
            );
            if ($rule) {
                $rules[] = $rule;
            }
        }

        $template = $this->approvedTemplateForUrl($url);
        if ($template) {
            $rule = $this->rulePayload(
                'xpath-template',
                $this->templateXpaths($template, 'name', $template->name_xpath),
                $this->templateXpaths($template, 'price', $template->price_xpath),
                $template->price_regex,
                (int) $template->id,
                $this->templateAdvancedRule($template)
            );
            if ($rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateOnlyScrapeRules(string $url): array
    {
        $template = $this->approvedTemplateForUrl($url);
        if (! $template) {
            return [];
        }

        $rule = $this->rulePayload(
            'xpath-template',
            $this->templateXpaths($template, 'name', $template->name_xpath),
            $this->templateXpaths($template, 'price', $template->price_xpath),
            $template->price_regex,
            (int) $template->id,
            $this->templateAdvancedRule($template)
        );

        return $rule ? [$rule] : [];
    }

    private function approvedTemplateForUrl(string $url): ?CompetitorSiteTemplate
    {
        $domain = CompetitorSite::normalizedDomainFromUrl($url);
        if (! $domain || ! Schema::hasTable('competitor_site_templates')) {
            return null;
        }

        $query = CompetitorSiteTemplate::query()
            ->where('domain', $domain)
            ->with(['scrapeXpaths' => function ($q) {
                $q->orderBy('type')->orderBy('position');
            }]);

        if (Schema::hasColumn('competitor_site_templates', 'is_approved')) {
            $query->where('is_approved', true);
        }

        return $query->first();
    }

    /**
     * @return list<string>
     */
    private function userXpaths(int $userId, string $type, ?string $primary): array
    {
        $fallbacks = Schema::hasTable('user_scrape_xpaths')
            ? UserScrapeXpath::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->orderBy('position')
                ->pluck('xpath')
                ->all()
            : [];

        return $this->cleanXpaths(array_merge([(string) $primary], $fallbacks));
    }

    /**
     * @return list<string>
     */
    private function siteXpaths(CompetitorSite $site, string $type, ?string $primary): array
    {
        $fallbacks = $site->scrapeXpaths
            ->where('type', $type)
            ->sortBy('position')
            ->pluck('xpath')
            ->all();

        return $this->cleanXpaths(array_merge([(string) $primary], $fallbacks));
    }

    /**
     * @return list<string>
     */
    private function templateXpaths(CompetitorSiteTemplate $template, string $type, ?string $primary): array
    {
        $fallbacks = $template->scrapeXpaths
            ->where('type', $type)
            ->sortBy('position')
            ->pluck('xpath')
            ->all();

        return $this->cleanXpaths(array_merge([(string) $primary], $fallbacks));
    }

    /**
     * @param list<string> $nameXpaths
     * @param list<string> $priceXpaths
     * @return array<string, mixed>|null
     */
    private function rulePayload(
        string $source,
        array $nameXpaths,
        array $priceXpaths,
        ?string $priceRegex,
        ?int $templateId = null,
        array $advanced = []
    ): ?array
    {
        $advanced = array_filter($advanced, function ($value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            if (is_bool($value)) {
                return $value;
            }

            return trim((string) ($value ?? '')) !== '';
        });

        if ($nameXpaths === [] && $priceXpaths === [] && $advanced === []) {
            return null;
        }

        return array_merge([
            'source' => $source,
            'nameXpaths' => $nameXpaths,
            'priceXpaths' => $priceXpaths,
            'priceRegex' => trim((string) ($priceRegex ?? '')) ?: null,
            'templateId' => $templateId,
        ], $advanced);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateAdvancedRule(CompetitorSiteTemplate $template): array
    {
        $table = $template->getTable();
        $has = fn (string $column): bool => Schema::hasColumn($table, $column);

        $headers = null;
        if ($has('api_headers')) {
            $rawHeaders = $template->api_headers;
            $headers = is_array($rawHeaders)
                ? collect($rawHeaders)
                    ->mapWithKeys(fn ($value, $key): array => [trim((string) $key) => trim((string) $value)])
                    ->filter(fn (string $value, string $key): bool => $key !== '' && $value !== '')
                    ->all()
                : null;
        }

        return [
            'useBrowser' => $has('use_browser') && (bool) $template->use_browser,
            'nameCss' => $has('name_css') ? $this->cleanCssLines((string) $template->name_css) : [],
            'priceCss' => $has('price_css') ? $this->cleanCssLines((string) $template->price_css) : [],
            'priceAttribute' => $has('price_attribute') ? (trim((string) $template->price_attribute) ?: null) : null,
            'apiUrlTemplate' => $has('api_url_template') ? (trim((string) $template->api_url_template) ?: null) : null,
            'apiNamePath' => $has('api_name_path') ? (trim((string) $template->api_name_path) ?: null) : null,
            'apiPricePath' => $has('api_price_path') ? (trim((string) $template->api_price_path) ?: null) : null,
            'apiHeaders' => $headers,
        ];
    }

    /**
     * @return list<string>
     */
    private function cleanCssLines(string $value): array
    {
        return collect(preg_split('/\R+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($selector): string => trim((string) $selector))
            ->filter(fn (string $selector): bool => $selector !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $xpaths
     * @return list<string>
     */
    private function cleanXpaths(array $xpaths): array
    {
        return collect($xpaths)
            ->map(fn ($xpath): string => trim((string) $xpath))
            ->filter(fn (string $xpath): bool => $xpath !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function applyProductResult(
        ScrapeAgentJob $job,
        array $payload,
        array $result,
        string $status,
        Carbon $fetchedAt
    ): void {
        $productId = (int) ($job->product_id ?: ($payload['productId'] ?? 0));
        $product = $productId > 0 ? Product::query()->find($productId) : null;
        if (! $product) {
            return;
        }

        if ($status === 'failed') {
            $this->markOwnScrapeFailure($product);
            $product->forceFill(['last_scraped_at' => $fetchedAt])->save();

            return;
        }

        $updates = ['last_scraped_at' => $fetchedAt];
        $name = trim((string) ($result['name'] ?? ''));
        if ($name !== '') {
            $updates['name'] = mb_substr($name, 0, 255);
        }

        $price = $this->priceFromResult($result);
        if ($status === 'no_price') {
            $price = 0;
        }
        if (! is_null($price)) {
            $updates['price'] = max(0, $price);
        }
        if (Schema::hasColumn('products', 'own_scrape_failed_since')) {
            $updates['own_scrape_failed_since'] = null;
        }

        $oldPrice = (int) $product->price;
        $product->forceFill($updates)->save();

        if (! is_null($price) && $price > 0 && $oldPrice !== $price) {
            $latest = ProductPriceHistory::query()
                ->where('product_id', $product->id)
                ->latest('fetched_at')
                ->first();
            if (! $latest || (int) $latest->price !== $price) {
                ProductPriceHistory::query()->create([
                    'product_id' => (int) $product->id,
                    'price' => $price,
                    'fetched_at' => $fetchedAt,
                ]);
            }
        }
    }

    private function applyCompetitorResult(
        ScrapeAgentJob $job,
        array $payload,
        array $result,
        string $status,
        Carbon $fetchedAt
    ): void {
        $competitorId = (int) ($job->competitor_id ?: ($payload['competitorId'] ?? 0));
        $competitor = $competitorId > 0
            ? Competitor::query()->with(['product', 'competitorSite'])->find($competitorId)
            : null;

        if (! $competitor) {
            return;
        }

        $product = $competitor->product;
        if ($product) {
            $product->forceFill(['last_scraped_at' => $fetchedAt])->save();
        }

        if ($status !== 'success') {
            $competitor->markPriceMissing();

            return;
        }

        $price = $this->priceFromResult($result);
        if (is_null($price) || $price <= 0) {
            $competitor->markPriceMissing();

            return;
        }

        $variantName = trim((string) ($result['variantName'] ?? ''));
        $variantKey = trim((string) ($result['variantKey'] ?? ''));
        $variantUpdates = [];
        if ($variantKey !== '') {
            $variantUpdates['variant_key'] = mb_substr($variantKey, 0, 255);
        }
        if ($variantName !== '') {
            $variantUpdates['variant_name'] = mb_substr($variantName, 0, 255);
        }
        if ($variantUpdates !== []) {
            $competitor->forceFill($variantUpdates)->save();
        }

        $competitor->markPriceAvailable();
        $latest = $competitor->prices()->latest('fetched_at')->first();
        if (! $latest || (int) $latest->price !== $price) {
            $previousPrice = $latest ? (int) $latest->price : null;
            CompetitorPrice::query()->create([
                'competitor_id' => (int) $competitor->id,
                'price' => $price,
                'fetched_at' => $fetchedAt,
            ]);

            if ($product) {
                (new AlertNotifier)->notifyOnCompetitorPriceChange($product, $competitor, $price, $previousPrice);
            }
        }
    }

    private function markOwnScrapeFailure(Product $product): void
    {
        if (! Schema::hasColumn('products', 'own_scrape_failed_since')) {
            return;
        }

        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $product->user_id]);
        $now = now();
        if (! $product->own_scrape_failed_since) {
            $product->forceFill(['own_scrape_failed_since' => $now])->save();

            return;
        }

        $enabled = Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_enabled')
            && (bool) $settings->auto_delete_failed_products_enabled;
        if (! $enabled) {
            return;
        }

        $days = Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_days')
            ? max(1, (int) ($settings->auto_delete_failed_products_days ?: 7))
            : 7;

        if ($product->own_scrape_failed_since->lte($now->copy()->subDays($days))) {
            $product->delete();
        }
    }

    private function fetchedAt(array $result): Carbon
    {
        $raw = trim((string) ($result['fetchedAt'] ?? ''));
        if ($raw === '') {
            return now();
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return now();
        }
    }

    private function priceFromResult(array $result): ?int
    {
        $raw = $result['price'] ?? null;
        if (is_numeric($raw)) {
            return max(0, (int) round((float) $raw));
        }

        return null;
    }
}
