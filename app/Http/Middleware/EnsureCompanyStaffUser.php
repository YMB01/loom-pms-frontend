<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ensures the Sanctum token belongs to a company staff User, not a Tenant portal user. */
class EnsureCompanyStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        return $next($request);
    }
}
