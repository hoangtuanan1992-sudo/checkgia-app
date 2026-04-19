<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsForShopeeApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('options')) {
            return $this->withCors(response('', 204), $request);
        }

        $response = $next($request);

        return $this->withCors($response, $request);
    }

    private function withCors(Response $response, Request $request): Response
    {
        $origin = (string) $request->headers->get('Origin', '*');
        if ($origin === '') {
            $origin = '*';
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin === 'null' ? '*' : $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
