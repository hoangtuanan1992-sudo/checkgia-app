<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopeeCheckEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && User::shopeeCheckEnabledForId($user->effectiveUserId()), 403);

        return $next($request);
    }
}
