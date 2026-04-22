<?php

namespace App\Http\Controllers\Shopee;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ShopeeAgent;
use App\Models\ShopeeCompetitor;
use App\Models\ShopeeCompetitorPrice;
use App\Models\ShopeeProduct;
use App\Models\ShopeeProductPrice;
use App\Services\AlertNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopeeAgentApiController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agent_key' => ['required', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['nullable', 'string', 'max:2000'],
            'last_error' => ['nullable', 'string', 'max:2000'],
            'last_task_url' => ['nullable', 'string', 'max:5000'],
        ]);

        $agent = ShopeeAgent::query()->firstOrNew([
            'agent_key' => (string) $data['agent_key'],
        ]);
        $agent->name = trim((string) ($data['name'] ?? $agent->name)) ?: $agent->name;
        $agent->version = (string) ($data['version'] ?? $agent->version);
        $agent->platform = (string) ($data['platform'] ?? $agent->platform);
        $agent->user_agent = (string) ($data['user_agent'] ?? $agent->user_agent);
        $agent->last_seen_at = now();
        if (array_key_exists('last_error', $data)) {
            $agent->last_error = trim((string) ($data['last_error'] ?? '')) ?: null;
        }
        if (array_key_exists('last_task_url', $data)) {
            $agent->last_task_url = trim((string) ($data['last_task_url'] ?? '')) ?: null;
        }
        if (! $agent->exists) {
            $agent->is_enabled = true;
            $agent->is_approved = false;
            $agent->mode = 'all';
            $agent->pair_code = strtoupper(Str::random(8));
        } elseif (! $agent->is_approved && ! $agent->pair_code) {
            $agent->pair_code = strtoupper(Str::random(8));
        }
        $agent->save();

        $err = strtolower((string) ($agent->last_error ?? ''));
        if (
            $err !== '' &&
            (str_contains($err, 'verify/traffic') || str_contains($err, 'captcha') || str_contains($err, 'unusual traffic') || str_contains($err, 'xác minh'))
        ) {
            ShopeeProduct::query()
                ->where('lease_agent_id', (int) $agent->id)
                ->update([
                    'lease_agent_id' => null,
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ]);

            ShopeeCompetitor::query()
                ->where('lease_agent_id', (int) $agent->id)
                ->update([
                    'lease_agent_id' => null,
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ]);
        }

        $setting = AppSetting::current();

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'shopee_enabled' => (bool) ($setting?->shopee_enabled ?? false),
            'update_interval_seconds' => (int) ($setting?->shopee_scrape_interval_seconds ?? 300),
            'rest_seconds_min' => (int) ($setting?->shopee_rest_seconds_min ?? 5),
            'rest_seconds_max' => (int) ($setting?->shopee_rest_seconds_max ?? 15),
            'agent' => [
                'id' => $agent->id,
                'is_enabled' => (bool) $agent->is_enabled,
                'is_approved' => (bool) $agent->is_approved,
                'pair_code' => $agent->is_approved ? null : $agent->pair_code,
                'api_token' => $agent->is_approved ? (string) ($agent->api_token ?? '') : null,
                'mode' => (string) $agent->mode,
                'assigned_user_id' => $agent->assigned_user_id ? (int) $agent->assigned_user_id : null,
            ],
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agent_key' => ['required', 'string', 'max:128'],
        ]);

        $agent = ShopeeAgent::query()->where('agent_key', (string) $data['agent_key'])->first();
        if (! $agent) {
            return response()->json(['message' => 'Unknown agent.'], 404);
        }

        $agent->last_seen_at = now();
        $agent->save();

        $setting = AppSetting::current();
        if (! $setting || ! $setting->shopee_enabled || ! $agent->is_enabled) {
            return response()->json(['sleep_seconds' => 60]);
        }

        $err = strtolower((string) ($agent->last_error ?? ''));
        if (
            $err !== '' &&
            (str_contains($err, 'verify/traffic') || str_contains($err, 'captcha') || str_contains($err, 'unusual traffic') || str_contains($err, 'xác minh'))
        ) {
            return response()->json(['sleep_seconds' => 600]);
        }

        $interval = max(10, (int) ($setting->shopee_scrape_interval_seconds ?? 300));
        $minRest = max(0, (int) ($setting->shopee_rest_seconds_min ?? 5));
        $maxRest = max($minRest, (int) ($setting->shopee_rest_seconds_max ?? 15));
        $maxChecks = (int) ($setting->shopee_max_checks_per_day ?? 24);
        $now = now();
        $leaseTtlSeconds = 300;

        if ($agent->last_scrape_at) {
            $nextAt = $agent->last_scrape_at->copy()->addSeconds($interval);
            if ($now->lt($nextAt)) {
                $today = $now->toDateString();

                $urgentProduct = ShopeeProduct::query()
                    ->where('is_enabled', true)
                    ->where('own_url', '!=', '')
                    ->whereNull('last_scraped_at')
                    ->whereRaw('(select count(*) from shopee_product_prices where shopee_product_prices.shopee_product_id = shopee_products.id and date(scraped_at) = ?) < ?', [$today, $maxChecks])
                    ->where(function ($q) use ($now) {
                        $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<', $now);
                    });

                if ($agent->mode === 'user' && $agent->assigned_user_id) {
                    $urgentProduct->where('user_id', (int) $agent->assigned_user_id);
                }

                $urgentCompetitor = ShopeeCompetitor::query()
                    ->where('is_enabled', true)
                    ->where('url', '!=', '')
                    ->whereNull('last_scraped_at')
                    ->whereRaw('(select count(*) from shopee_competitor_prices where shopee_competitor_prices.shopee_competitor_id = shopee_competitors.id and date(scraped_at) = ?) < ?', [$today, $maxChecks])
                    ->where(function ($q) use ($now) {
                        $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<', $now);
                    })
                    ->whereHas('product', function ($q) use ($agent) {
                        $q->where('is_enabled', true);
                        if ($agent->mode === 'user' && $agent->assigned_user_id) {
                            $q->where('user_id', (int) $agent->assigned_user_id);
                        }
                    });

                $hasUrgent = $urgentProduct->exists() || $urgentCompetitor->exists();
                if (! $hasUrgent) {
                    return response()->json(['sleep_seconds' => max(1, $now->diffInSeconds($nextAt))]);
                }
            }
        }

        $today = $now->toDateString();

        $activeLease = ShopeeProduct::query()
            ->where('lease_agent_id', (int) $agent->id)
            ->where('lease_expires_at', '>', $now)
            ->exists()
            || ShopeeCompetitor::query()
                ->where('lease_agent_id', (int) $agent->id)
                ->where('lease_expires_at', '>', $now)
                ->exists();

        if ($activeLease) {
            return response()->json(['sleep_seconds' => 15]);
        }

        $payload = DB::transaction(function () use ($agent, $today, $maxChecks, $now, $leaseTtlSeconds) {
            $availableLease = function ($q) use ($now) {
                $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<', $now);
            };

            $productQuery = ShopeeProduct::query()
                ->where('is_enabled', true)
                ->where('own_url', '!=', '')
                ->whereRaw('(select count(*) from shopee_product_prices where shopee_product_prices.shopee_product_id = shopee_products.id and date(scraped_at) = ?) < ?', [$today, $maxChecks])
                ->where($availableLease);

            if ($agent->mode === 'user' && $agent->assigned_user_id) {
                $productQuery->where('user_id', (int) $agent->assigned_user_id);
            }

            $product = $productQuery
                ->orderByRaw('last_scraped_at is null desc')
                ->orderByDesc('created_at')
                ->orderBy('last_scraped_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $competitorQuery = ShopeeCompetitor::query()
                ->where('is_enabled', true)
                ->where('url', '!=', '')
                ->whereRaw('(select count(*) from shopee_competitor_prices where shopee_competitor_prices.shopee_competitor_id = shopee_competitors.id and date(scraped_at) = ?) < ?', [$today, $maxChecks])
                ->where($availableLease)
                ->whereHas('product', function ($q) use ($agent) {
                    $q->where('is_enabled', true);
                    if ($agent->mode === 'user' && $agent->assigned_user_id) {
                        $q->where('user_id', (int) $agent->assigned_user_id);
                    }
                })
                ->with(['product:id,user_id,price_pick']);

            $competitor = $competitorQuery
                ->orderByRaw('last_scraped_at is null desc')
                ->orderByDesc('created_at')
                ->orderBy('last_scraped_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $task = null;
            $leaseToken = null;
            $leaseExpiresAt = null;

            if ($product && $competitor) {
                $pTs = $product->last_scraped_at?->timestamp ?? 0;
                $cTs = $competitor->last_scraped_at?->timestamp ?? 0;

                if ($pTs <= $cTs) {
                    $leaseToken = Str::random(48);
                    $leaseExpiresAt = $now->copy()->addSeconds($leaseTtlSeconds);
                    $product->lease_agent_id = (int) $agent->id;
                    $product->lease_token = $leaseToken;
                    $product->lease_expires_at = $leaseExpiresAt;
                    $product->last_assigned_at = $now;
                    $product->save();

                    $task = [
                        'type' => 'product',
                        'product_id' => (int) $product->id,
                        'user_id' => (int) $product->user_id,
                        'url' => (string) $product->own_url,
                        'price_pick' => (string) ($product->price_pick ?: 'low'),
                        'lease_token' => $leaseToken,
                        'lease_expires_at' => $leaseExpiresAt->toIso8601String(),
                    ];
                } else {
                    $leaseToken = Str::random(48);
                    $leaseExpiresAt = $now->copy()->addSeconds($leaseTtlSeconds);
                    $competitor->lease_agent_id = (int) $agent->id;
                    $competitor->lease_token = $leaseToken;
                    $competitor->lease_expires_at = $leaseExpiresAt;
                    $competitor->last_assigned_at = $now;
                    $competitor->save();

                    $task = [
                        'type' => 'competitor',
                        'competitor_id' => (int) $competitor->id,
                        'product_id' => (int) $competitor->shopee_product_id,
                        'user_id' => (int) ($competitor->product?->user_id ?? 0),
                        'url' => (string) $competitor->url,
                        'price_pick' => in_array($competitor->price_pick, ['low', 'high'], true) ? (string) $competitor->price_pick : (string) (($competitor->product?->price_pick ?: 'low')),
                        'lease_token' => $leaseToken,
                        'lease_expires_at' => $leaseExpiresAt->toIso8601String(),
                    ];
                }
            } elseif ($competitor) {
                $leaseToken = Str::random(48);
                $leaseExpiresAt = $now->copy()->addSeconds($leaseTtlSeconds);
                $competitor->lease_agent_id = (int) $agent->id;
                $competitor->lease_token = $leaseToken;
                $competitor->lease_expires_at = $leaseExpiresAt;
                $competitor->last_assigned_at = $now;
                $competitor->save();

                $task = [
                    'type' => 'competitor',
                    'competitor_id' => (int) $competitor->id,
                    'product_id' => (int) $competitor->shopee_product_id,
                    'user_id' => (int) ($competitor->product?->user_id ?? 0),
                    'url' => (string) $competitor->url,
                    'price_pick' => in_array($competitor->price_pick, ['low', 'high'], true) ? (string) $competitor->price_pick : (string) (($competitor->product?->price_pick ?: 'low')),
                    'lease_token' => $leaseToken,
                    'lease_expires_at' => $leaseExpiresAt->toIso8601String(),
                ];
            } elseif ($product) {
                $leaseToken = Str::random(48);
                $leaseExpiresAt = $now->copy()->addSeconds($leaseTtlSeconds);
                $product->lease_agent_id = (int) $agent->id;
                $product->lease_token = $leaseToken;
                $product->lease_expires_at = $leaseExpiresAt;
                $product->last_assigned_at = $now;
                $product->save();

                $task = [
                    'type' => 'product',
                    'product_id' => (int) $product->id,
                    'user_id' => (int) $product->user_id,
                    'url' => (string) $product->own_url,
                    'price_pick' => (string) ($product->price_pick ?: 'low'),
                    'lease_token' => $leaseToken,
                    'lease_expires_at' => $leaseExpiresAt->toIso8601String(),
                ];
            }

            return $task ? ['task' => $task] : null;
        });

        $task = $payload['task'] ?? null;

        if (! $task) {
            return response()->json(['sleep_seconds' => 60]);
        }

        $agent->last_scrape_at = $now;
        $agent->save();

        return response()->json([
            'sleep_seconds' => random_int($minRest, $maxRest),
            'task' => $task,
        ]);
    }

    public function report(Request $request, AlertNotifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'agent_key' => ['required', 'string', 'max:128'],
            'task_type' => ['nullable', 'string', 'in:product,competitor'],
            'product_id' => ['nullable', 'integer'],
            'competitor_id' => ['nullable', 'integer'],
            'lease_token' => ['nullable', 'string', 'max:64'],
            'price' => ['required', 'integer', 'min:0'],
            'scraped_at' => ['nullable', 'date'],
            'raw_text' => ['nullable', 'string', 'max:20000'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $agent = ShopeeAgent::query()->where('agent_key', (string) $data['agent_key'])->first();
        if (! $agent) {
            return response()->json(['message' => 'Unknown agent.'], 404);
        }

        $scrapedAt = isset($data['scraped_at']) ? now()->parse($data['scraped_at']) : now();
        $price = (int) $data['price'];
        $rawText = isset($data['raw_text']) ? trim((string) $data['raw_text']) : null;
        $name = isset($data['name']) ? trim((string) $data['name']) : null;
        if ($name === '') {
            $name = null;
        }

        $taskType = (string) ($data['task_type'] ?? '');
        if ($taskType === 'competitor' || (isset($data['competitor_id']) && $data['competitor_id'])) {
            $competitor = ShopeeCompetitor::query()->find((int) ($data['competitor_id'] ?? 0));
            if (! $competitor) {
                return response()->json(['message' => 'Competitor not found.'], 404);
            }

            $leaseToken = (string) ($data['lease_token'] ?? '');
            if (
                $leaseToken === '' ||
                (int) ($competitor->lease_agent_id ?? 0) !== (int) $agent->id ||
                ! hash_equals((string) ($competitor->lease_token ?? ''), $leaseToken) ||
                ($competitor->lease_expires_at && now()->gt($competitor->lease_expires_at))
            ) {
                return response()->json(['message' => 'Task lease expired.'], 409);
            }

            $previousPrice = is_null($competitor->last_price) ? null : (int) $competitor->last_price;

            ShopeeCompetitorPrice::create([
                'shopee_competitor_id' => (int) $competitor->id,
                'price' => $price,
                'scraped_at' => $scrapedAt,
                'raw_text' => $rawText ?: null,
            ]);

            $competitor->last_price = $price;
            $competitor->last_scraped_at = $scrapedAt;
            $competitor->lease_agent_id = null;
            $competitor->lease_token = null;
            $competitor->lease_expires_at = null;
            $competitor->save();

            $product = ShopeeProduct::query()->find((int) $competitor->shopee_product_id);
            if ($product && $product->is_enabled && $competitor->is_enabled) {
                $notifier->notifyOnShopeeCompetitorPriceChange($product, $competitor->loadMissing('shop'), $price, $previousPrice);
            }

            if ($name) {
                if ($product && ! $product->name) {
                    $product->name = $name;
                    $product->save();
                }
            }
        } else {
            $product = ShopeeProduct::query()->find((int) ($data['product_id'] ?? 0));
            if (! $product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            $leaseToken = (string) ($data['lease_token'] ?? '');
            if (
                $leaseToken === '' ||
                (int) ($product->lease_agent_id ?? 0) !== (int) $agent->id ||
                ! hash_equals((string) ($product->lease_token ?? ''), $leaseToken) ||
                ($product->lease_expires_at && now()->gt($product->lease_expires_at))
            ) {
                return response()->json(['message' => 'Task lease expired.'], 409);
            }

            ShopeeProductPrice::create([
                'shopee_product_id' => (int) $product->id,
                'price' => $price,
                'scraped_at' => $scrapedAt,
                'raw_text' => $rawText ?: null,
            ]);

            $product->last_price = $price;
            $product->last_scraped_at = $scrapedAt;
            $product->lease_agent_id = null;
            $product->lease_token = null;
            $product->lease_expires_at = null;
            if ($name && ! $product->name) {
                $product->name = $name;
            }
            $product->save();
        }

        $agent->last_seen_at = now();
        $agent->last_report_at = now();
        $agent->last_error = null;
        $agent->save();

        return response()->json(['ok' => true]);
    }
}
