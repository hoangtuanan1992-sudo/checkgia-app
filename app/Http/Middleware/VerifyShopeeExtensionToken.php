<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopeeExtensionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = AppSetting::current();
        if (! $setting || ! $setting->shopee_enabled || ! $setting->shopee_extension_token) {
            return response()->json(['message' => 'Shopee extension is disabled.'], 403);
        }

        $token = $this->extractBearer($request) ?? (string) $request->header('X-EXT-TOKEN', '');
        if (! hash_equals((string) $setting->shopee_extension_token, (string) $token)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

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
