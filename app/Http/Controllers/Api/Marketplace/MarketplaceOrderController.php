<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Enums\MarketplaceOrderStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\Property;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceOrderController extends Controller
{
    use InteractsWithCompany;

    public function index(): JsonResponse
    {
        $companyId = $this->companyId();

        $orders = MarketplaceOrder::query()
            ->where('company_id', $companyId)
            ->with(['property', 'vendor', 'product'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'property' => $o->property?->name,
                'vendor' => $o->vendor?->name,
                'product' => $o->product?->name,
                'quantity' => $o->quantity,
                'total_price' => (float) $o->total_price,
                'status' => $o->status->value,
                'created_at' => $o->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success(['orders' => $orders], '');
    }

    public function store(Request $request, InAppNotificationService $notify): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'product_id' => ['required', 'integer', 'exists:marketplace_products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'preferred_date' => ['nullable', 'date'],
            'special_instructions' => ['nullable', 'string'],
        ]);

        $companyId = $this->companyId();

        Property::query()->where('company_id', $companyId)->whereKey($data['property_id'])->firstOrFail();

        $product = MarketplaceProduct::query()->with('vendor')->findOrFail($data['product_id']);
        if (! $product->is_active || ! $product->vendor?->is_approved) {
            return ApiResponse::error('Product unavailable.', 422);
        }

        $qty = (int) $data['quantity'];
        $unit = (float) $product->price;
        $total = round($unit * $qty, 2);

        $order = MarketplaceOrder::query()->create([
            'company_id' => $companyId,
            'property_id' => $data['property_id'],
            'vendor_id' => $product->vendor_id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => $unit,
            'total_price' => $total,
            'preferred_date' => $data['preferred_date'] ?? null,
            'special_instructions' => $data['special_instructions'] ?? null,
            'status' => MarketplaceOrderStatus::Pending,
        ]);

        $notify->notifyManagers(
            $companyId,
            'Marketplace order placed',
            "Order #{$order->id} — {$product->name} ({$order->total_price}).",
        );

        return ApiResponse::success(['order_id' => $order->id], 'Order placed.', 201);
    }

    public function update(Request $request, MarketplaceOrder $marketplace_order): JsonResponse
    {
        $companyId = $this->companyId();
        if ($marketplace_order->company_id !== $companyId) {
            return ApiResponse::error('Not found.', 404);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $marketplace_order->update([
            'status' => MarketplaceOrderStatus::from($data['status']),
            'notes' => $data['notes'] ?? $marketplace_order->notes,
        ]);

        return ApiResponse::success(['order' => ['id' => $marketplace_order->id, 'status' => $marketplace_order->status->value]], 'Order updated.');
    }
}
