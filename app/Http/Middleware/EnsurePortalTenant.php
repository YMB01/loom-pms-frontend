<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        if (! $u instanceof Tenant) {
            return ApiResponse::error('Tenant session required.', 403);
        }

        return $next($request);
    }
}
