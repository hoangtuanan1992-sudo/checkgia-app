<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ScrapeAgentJob;
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

            if ($job->competitor_id || ! empty($payload['competitorId'])) {
                $this->applyCompetitorResult($job, $payload, $result, $status, $fetchedAt);
            } else {
                $this->applyProductResult($job, $payload, $result, $status, $fetchedAt);
            }

            $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];
            $job->forceFill([
                'status' => $status === 'failed' ? 'failed' : 'done',
                'leased_by_agent_id' => null,
                'lease_token' => null,
                'leased_at' => null,
                'lease_expires_at' => null,
                'finished_at' => now(),
                'last_error_code' => isset($error['code']) ? mb_substr((string) $error['code'], 0, 80) : null,
                'last_error' => isset($error['message']) ? mb_substr((string) $error['message'], 0, 4000) : null,
                'result_payload' => $payload,
            ])->save();
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

    private function refreshDueJobs(int $targetQueueSize): void
    {
        $ready = ScrapeAgentJob::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->count();

        if ($ready >= $targetQueueSize) {
            return;
        }

        $created = 0;
        $now = now('Asia/Ho_Chi_Minh');
        $hasScheduleTimes = Schema::hasColumn('user_scrape_settings', 'scrape_schedule_times');
        $settings = UserScrapeSetting::query()->get()->keyBy('user_id');
        $userIds = Product::query()
            ->whereNotNull('product_url')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            if ($created >= $targetQueueSize) {
                break;
            }

            $setting = $settings->get((int) $userId) ?? new UserScrapeSetting([
                'user_id' => (int) $userId,
                'scrape_interval_minutes' => 10,
                'scrape_schedule_times' => '',
            ]);
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

                if ($this->ensureProductJob($product)) {
                    $created++;
                }

                foreach ($product->competitors as $competitor) {
                    if ($this->ensureCompetitorJob($competitor)) {
                        $created++;
                    }
                }
            }
        }
    }

    private function ensureProductJob(Product $product): bool
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
            'priority' => 10,
        ]);
    }

    private function ensureCompetitorJob(Competitor $competitor): bool
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
            'priority' => 50,
        ]);
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
            'useBrowser' => false,
            'timeoutSeconds' => (int) config('services.checkgia_agent.job_timeout_seconds', 60),
            'attempt' => (int) $job->attempts,
        ];
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
