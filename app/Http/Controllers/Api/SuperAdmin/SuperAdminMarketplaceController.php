<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\MarketplaceOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceVendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminMarketplaceController extends Controller
{
    public function vendorsIndex(): JsonResponse
    {
        $rows = MarketplaceVendor::query()->with('category')->orderBy('name')->get()->map(fn ($v) => [
            'id' => $v->id,
            'company_id' => $v->company_id,
            'category_id' => $v->category_id,
            'name' => $v->name,
            'is_active' => $v->is_active,
            'is_approved' => $v->is_approved,
            'rating' => (float) $v->rating,
        ]);

        return ApiResponse::success(['vendors' => $rows], '');
    }

    public function vendorsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'category_id' => ['required', 'exists:marketplace_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
            'is_approved' => ['boolean'],
        ]);

        $v = MarketplaceVendor::query()->create($data);

        return ApiResponse::success(['vendor' => ['id' => $v->id]], 'Vendor created.', 201);
    }

    public function vendorsUpdate(Request $request, MarketplaceVendor $vendor): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'category_id' => ['sometimes', 'exists:marketplace_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
            'is_approved' => ['boolean'],
        ]);

        $vendor->update($data);

        return ApiResponse::success([], 'Vendor updated.');
    }

    public function vendorsDestroy(MarketplaceVendor $vendor): JsonResponse
    {
        $vendor->delete();

        return ApiResponse::success([], 'Vendor deleted.');
    }

    public function vendorsApprove(MarketplaceVendor $vendor): JsonResponse
    {
        $vendor->update([
            'is_approved' => true,
            'approved_by' => null,
        ]);

        return ApiResponse::success([], 'Vendor approved.');
    }

    public function productsIndex(): JsonResponse
    {
        $rows = MarketplaceProduct::query()->with(['vendor', 'category'])->orderBy('name')->get();

        return ApiResponse::success(['products' => $rows], '');
    }

    public function productsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:marketplace_vendors,id'],
            'category_id' => ['required', 'exists:marketplace_categories,id'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string'],
            'availability' => ['required', 'string'],
            'badge' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $p = MarketplaceProduct::query()->create($data);

        return ApiResponse::success(['product' => ['id' => $p->id]], 'Product created.', 201);
    }

    public function productsUpdate(Request $request, MarketplaceProduct $product): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['sometimes', 'exists:marketplace_vendors,id'],
            'category_id' => ['sometimes', 'exists:marketplace_categories,id'],
            'name' => ['sometimes', 'string'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric'],
            'unit' => ['sometimes', 'string'],
            'availability' => ['sometimes', 'string'],
            'badge' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $product->update($data);

        return ApiResponse::success([], 'Product updated.');
    }

    public function productsDestroy(MarketplaceProduct $product): JsonResponse
    {
        $product->delete();

        return ApiResponse::success([], 'Product deleted.');
    }

    public function ordersIndex(): JsonResponse
    {
        $orders = MarketplaceOrder::query()
            ->with(['company', 'property', 'vendor', 'product'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return ApiResponse::success(['orders' => $orders], '');
    }

    public function categoriesIndex(): JsonResponse
    {
        return ApiResponse::success([
            'categories' => MarketplaceCategory::query()->orderBy('display_order')->get(),
        ], '');
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'icon' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'display_order' => ['integer'],
        ]);

        $c = MarketplaceCategory::query()->create($data);

        return ApiResponse::success(['category' => ['id' => $c->id]], 'Category created.', 201);
    }

    public function categoriesUpdate(Request $request, MarketplaceCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'icon' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'display_order' => ['integer'],
        ]);

        $category->update($data);

        return ApiResponse::success([], 'Category updated.');
    }
}
