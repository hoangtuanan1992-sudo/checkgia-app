<?php

namespace App\Http\Controllers\Shopee;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ShopeeAgent;
use App\Models\ShopeeItem;
use App\Models\ShopeePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        $agent = ShopeeAgent::query()->firstOrNew([
            'agent_key' => (string) $data['agent_key'],
        ]);
        $agent->name = trim((string) ($data['name'] ?? $agent->name)) ?: $agent->name;
        $agent->version = (string) ($data['version'] ?? $agent->version);
        $agent->platform = (string) ($data['platform'] ?? $agent->platform);
        $agent->user_agent = (string) ($data['user_agent'] ?? $agent->user_agent);
        $agent->last_seen_at = now();
        if (! $agent->exists) {
            $agent->is_enabled = true;
            $agent->mode = 'all';
        }
        $agent->save();

        $setting = AppSetting::current();

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'shopee_enabled' => (bool) ($setting?->shopee_enabled ?? false),
            'scrape_interval_seconds' => (int) ($setting?->shopee_scrape_interval_seconds ?? 300),
            'rest_seconds_min' => (int) ($setting?->shopee_rest_seconds_min ?? 5),
            'rest_seconds_max' => (int) ($setting?->shopee_rest_seconds_max ?? 15),
            'agent' => [
                'id' => $agent->id,
                'is_enabled' => (bool) $agent->is_enabled,
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

        $interval = max(10, (int) ($setting->shopee_scrape_interval_seconds ?? 300));
        $minRest = max(0, (int) ($setting->shopee_rest_seconds_min ?? 5));
        $maxRest = max($minRest, (int) ($setting->shopee_rest_seconds_max ?? 15));

        if ($agent->last_scrape_at) {
            $nextAt = $agent->last_scrape_at->copy()->addSeconds($interval);
            if (now()->lt($nextAt)) {
                return response()->json(['sleep_seconds' => max(1, now()->diffInSeconds($nextAt))]);
            }
        }

        $q = ShopeeItem::query()->where('is_enabled', true);
        if ($agent->mode === 'user' && $agent->assigned_user_id) {
            $q->where('user_id', (int) $agent->assigned_user_id);
        }

        $item = $q
            ->orderByRaw('last_scraped_at is null desc')
            ->orderBy('last_scraped_at')
            ->orderBy('id')
            ->first();

        if (! $item) {
            return response()->json(['sleep_seconds' => 60]);
        }

        $agent->last_scrape_at = now();
        $agent->save();

        return response()->json([
            'sleep_seconds' => random_int($minRest, $maxRest),
            'task' => [
                'item_id' => (int) $item->id,
                'user_id' => (int) $item->user_id,
                'url' => (string) $item->url,
            ],
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agent_key' => ['required', 'string', 'max:128'],
            'item_id' => ['required', 'integer'],
            'price' => ['required', 'integer', 'min:0'],
            'scraped_at' => ['nullable', 'date'],
            'raw_text' => ['nullable', 'string', 'max:20000'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $agent = ShopeeAgent::query()->where('agent_key', (string) $data['agent_key'])->first();
        if (! $agent) {
            return response()->json(['message' => 'Unknown agent.'], 404);
        }

        $item = ShopeeItem::query()->find((int) $data['item_id']);
        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $scrapedAt = isset($data['scraped_at']) ? now()->parse($data['scraped_at']) : now();
        $price = (int) $data['price'];
        $rawText = isset($data['raw_text']) ? trim((string) $data['raw_text']) : null;
        $name = isset($data['name']) ? trim((string) $data['name']) : null;
        if ($name === '') {
            $name = null;
        }

        ShopeePrice::create([
            'shopee_item_id' => (int) $item->id,
            'price' => $price,
            'scraped_at' => $scrapedAt,
            'raw_text' => $rawText ?: null,
        ]);

        $item->last_price = $price;
        $item->last_scraped_at = $scrapedAt;
        if ($name && ! $item->name) {
            $item->name = $name;
        }
        $item->save();

        $agent->last_seen_at = now();
        $agent->save();

        return response()->json(['ok' => true]);
    }
}
