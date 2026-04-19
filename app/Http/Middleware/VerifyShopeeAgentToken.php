<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Models\ShopeeAgent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopeeAgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = AppSetting::current();
        if (! $setting || ! $setting->shopee_enabled) {
            return response()->json(['message' => 'Shopee is disabled.'], 403);
        }

        $agentKey = (string) $request->input('agent_key', '');
        if ($agentKey === '') {
            return response()->json(['message' => 'Missing agent_key.'], 422);
        }

        $agent = ShopeeAgent::query()->where('agent_key', $agentKey)->first();
        if (! $agent) {
            ShopeeAgent::query()->create([
                'agent_key' => $agentKey,
                'is_enabled' => true,
                'is_approved' => false,
                'mode' => 'all',
                'pair_code' => strtoupper(Str::random(8)),
                'last_seen_at' => now(),
            ]);

            return response()->json(['message' => 'Agent is not approved.'], 403);
        }

        if (! $agent->is_enabled) {
            return response()->json(['message' => 'Agent disabled.'], 403);
        }

        if (! $agent->is_approved || ! $agent->api_token) {
            return response()->json(['message' => 'Agent is not approved.'], 403);
        }

        $token = $this->extractBearer($request);
        if (! $token || ! hash_equals((string) $agent->api_token, (string) $token)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $agent->last_seen_at = now();
        $agent->save();

        return $next($request);
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = (string) $request->header('Authorization', '');
        if ($auth === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m) === 1) {
            return trim((string) ($m[1] ?? ''));
        }

        return null;
    }
}
