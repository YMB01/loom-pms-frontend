<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class SuperAdminPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn (Plan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => (string) $p->price,
                'max_properties' => $p->max_properties,
                'max_units' => $p->max_units,
                'max_tenants' => $p->max_tenants,
                'features' => is_array($p->features) ? $p->features : [],
            ]);

        return ApiResponse::success(['plans' => $plans], '');
    }
}
