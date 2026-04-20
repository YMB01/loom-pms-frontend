<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $key = 'super_admin:'.hash('sha256', $token);
        if (! Cache::has($key)) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
