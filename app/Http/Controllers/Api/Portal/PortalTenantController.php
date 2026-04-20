<?php

namespace App\Http\Controllers\Api\Portal;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseRenewalRequest;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalTenantController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $tenant->load(['company']);

        $balance = $this->computeBalance($tenant);

        $lease = $tenant->leases()
            ->where('status', LeaseStatus::Active)
            ->with(['unit.property'])
            ->first();

        return ApiResponse::success([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'company' => $tenant->company?->only(['id', 'name', 'currency']),
            ],
            'balance_due' => round($balance, 2),
            'lease' => $lease ? [
                'id' => $lease->id,
                'start_date' => $lease->start_date?->toDateString(),
                'end_date' => $lease->end_date?->toDateString(),
                'rent_amount' => (float) $lease->rent_amount,
                'unit' => $lease->unit?->unit_number,
                'property' => $lease->unit?->property?->name,
            ] : null,
        ], '');
    }

    public function invoices(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $rows = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('due_date')
            ->limit(100)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'amount' => (float) $i->amount,
                'due_date' => $i->due_date?->toDateString(),
                'status' => $i->status->value,
            ]);

        return ApiResponse::success(['invoices' => $rows], '');
    }

    public function payments(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $rows = Payment::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'reference' => $p->reference,
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success(['payments' => $rows], '');
    }

    public function payRent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:telebirr,cbe_birr,cash,card'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->user();

        $invoice = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($data['invoice_id'])
            ->firstOrFail();

        $payment = DB::transaction(function () use ($data, $invoice, $tenant) {
            $p = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'tenant_id' => $tenant->id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => 'PORTAL-'.now()->format('YmdHis'),
            ]);
            $invoice->syncStatusFromPayments();

            return $p;
        });

        return ApiResponse::success(['payment_id' => $payment->id], 'Payment recorded.', 201);
    }

    public function maintenanceIndex(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $propertyIds = $tenant->leases()
            ->where('status', LeaseStatus::Active)
            ->with('unit')
            ->get()
            ->pluck('unit.property_id')
            ->filter()
            ->unique()
            ->values();

        $rows = MaintenanceRequest::query()
            ->whereIn('property_id', $propertyIds)
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::success(['requests' => $rows], '');
    }

    public function maintenanceStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->user();

        $lease = $tenant->leases()
            ->where('status', LeaseStatus::Active)
            ->with('unit')
            ->firstOrFail();

        $propertyId = $lease->unit?->property_id;
        if (! $propertyId) {
            return ApiResponse::error('No property context for maintenance.', 422);
        }

        $m = MaintenanceRequest::query()->create([
            'property_id' => $propertyId,
            'tenant_id' => $tenant->id,
            'unit' => $lease->unit?->unit_number,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => MaintenancePriority::from($data['priority'] ?? 'medium'),
            'status' => MaintenanceStatus::Open,
        ]);

        return ApiResponse::success(['id' => $m->id], 'Request submitted.', 201);
    }

    public function lease(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $lease = $tenant->leases()
            ->where('status', LeaseStatus::Active)
            ->with(['unit.property'])
            ->firstOrFail();

        $pdfUrl = null;
        if ($lease->lease_document) {
            $pdfUrl = Storage::disk('public')->url($lease->lease_document);
        }

        return ApiResponse::success([
            'lease' => [
                'id' => $lease->id,
                'start_date' => $lease->start_date?->toDateString(),
                'end_date' => $lease->end_date?->toDateString(),
                'rent_amount' => (float) $lease->rent_amount,
                'deposit_amount' => (float) $lease->deposit_amount,
                'status' => $lease->status->value,
                'lease_document_url' => $pdfUrl,
            ],
        ], '');
    }

    public function leaseRenewal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->user();

        $lease = $tenant->leases()
            ->where('status', LeaseStatus::Active)
            ->firstOrFail();

        $r = LeaseRenewalRequest::query()->create([
            'tenant_id' => $tenant->id,
            'lease_id' => $lease->id,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        return ApiResponse::success(['request_id' => $r->id], 'Renewal request submitted.', 201);
    }

    private function computeBalance(Tenant $tenant): float
    {
        $invoices = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->withSum('payments', 'amount')
            ->get();

        $due = 0.0;
        foreach ($invoices as $inv) {
            $paid = (float) ($inv->payments_sum_amount ?? 0);
            $due += max(0, (float) $inv->amount - $paid);
        }

        return $due;
    }
}
