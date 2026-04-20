<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceVendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceCatalogController extends Controller
{
    use InteractsWithCompany;

    public function categories(): JsonResponse
    {
        $rows = MarketplaceCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'description' => $c->description,
            ]);

        return ApiResponse::success(['categories' => $rows], '');
    }

    public function vendors(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:marketplace_categories,id'],
        ]);

        $companyId = $this->companyId();

        $q = MarketplaceVendor::query()
            ->where('is_active', true)
            ->where('is_approved', true)
            ->where(function ($v) use ($companyId): void {
                $v->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->with('category');

        if ($request->filled('category_id')) {
            $q->where('category_id', (int) $request->query('category_id'));
        }

        $rows = $q->orderBy('name')->get()->map(fn ($v) => [
            'id' => $v->id,
            'category_id' => $v->category_id,
            'category_name' => $v->category?->name,
            'name' => $v->name,
            'description' => $v->description,
            'logo' => $v->logo,
            'phone' => $v->phone,
            'email' => $v->email,
            'rating' => (float) $v->rating,
        ]);

        return ApiResponse::success(['vendors' => $rows], '');
    }

    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:marketplace_categories,id'],
            'vendor_id' => ['sometimes', 'integer', 'exists:marketplace_vendors,id'],
        ]);

        $q = MarketplaceProduct::query()
            ->where('is_active', true)
            ->with(['vendor', 'category']);

        if ($request->filled('category_id')) {
            $q->where('category_id', (int) $request->query('category_id'));
        }
        if ($request->filled('vendor_id')) {
            $q->where('vendor_id', (int) $request->query('vendor_id'));
        }

        $rows = $q->orderBy('name')->get()->map(fn ($p) => [
            'id' => $p->id,
            'vendor_id' => $p->vendor_id,
            'vendor_name' => $p->vendor?->name,
            'category_id' => $p->category_id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => (float) $p->price,
            'unit' => $p->unit,
            'availability' => $p->availability,
            'badge' => $p->badge,
        ]);

        return ApiResponse::success(['products' => $rows], '');
    }
}
