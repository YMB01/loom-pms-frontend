<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\User;
use App\Services\PlanLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimits
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $company = Company::query()->with(['subscription.plan'])->findOrFail($user->company_id);

        $service = app(PlanLimitService::class);
        $resp = match ($resource) {
            'property' => $service->ensureCanAddProperty($company),
            'unit' => $service->ensureCanAddUnit($company),
            'tenant' => $service->ensureCanAddTenant($company),
            default => null,
        };

        return $resp ?? $next($request);
    }
}
