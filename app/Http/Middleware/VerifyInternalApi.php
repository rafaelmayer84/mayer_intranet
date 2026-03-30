<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyInternalApi
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Se o usuario tem sessao web autenticada, permite
        if ($request->user()) {
            return $next($request);
        }

        // 2. Se tem Bearer token valido, permite
        $bearer = $request->bearerToken();
        $expected = config('services.internal_api.token');
        if ($bearer && $expected && hash_equals($expected, $bearer)) {
            return $next($request);
        }

        // 3. Se vem de localhost (cron/artisan), permite
        $ip = $request->ip();
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return $next($request);
        }

        // Este middleware so e aplicado em rotas API — sempre JSON
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
