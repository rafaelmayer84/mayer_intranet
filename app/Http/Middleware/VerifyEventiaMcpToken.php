<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyEventiaMcpToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearer   = $request->bearerToken();
        $expected = env('EVIDENTIA_MCP_TOKEN');

        if (!$bearer || !$expected || !hash_equals($expected, $bearer)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
