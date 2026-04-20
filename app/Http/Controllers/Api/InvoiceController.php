<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Invoice\GenerateMonthlyInvoicesRequest;
use App\Http\Requests\Api\Invoice\IndexInvoiceRequest;
use App\Http\Requests\Api\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Api\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = Invoice::query()
            ->forCompany($companyId)
            ->with(['lease.tenant', 'lease.unit.property', 'tenant'])
            ->orderByDesc('due_date');

        if ($request->filled('status')) {
            $query->where('status', InvoiceStatus::from($request->validated('status')));
        }

        if ($request->filled('property_id')) {
            $propertyId = (int) $request->validated('property_id');
            $query->whereHas('lease.unit', fn ($q) => $q->where('property_id', $propertyId));
        }

        if ($request->filled('search')) {
            $raw = trim((string) $request->validated('search'));
            $term = '%'.addcslashes($raw, '%_\\').'%';
            $query->where(function ($q) use ($term, $raw): void {
                $q->whereHas('tenant', fn ($t) => $t->where('name', 'like', $term));
                if (ctype_digit($raw)) {
                    $q->orWhere('id', (int) $raw);
                }
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, InvoiceResource::class),
            ''
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = isset($data['status'])
            ? InvoiceStatus::from($data['status'])
            : InvoiceStatus::Pending;

        $invoice = Invoice::query()->create($data);
        $invoice->load(['lease', 'tenant']);

        return ApiResponse::success(
            ['invoice' => InvoiceResource::make($invoice)->resolve()],
            'Invoice created.',
            201
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'tenant',
            'payments',
            'lease.tenant',
            'lease.unit',
        ]);

        return ApiResponse::success(
            ['invoice' => InvoiceResource::make($invoice)->resolve()],
            ''
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['status'])) {
            $data['status'] = InvoiceStatus::from($data['status']);
        }

        $invoice->update($data);
        $invoice->load(['lease', 'tenant', 'payments']);

        return ApiResponse::success(
            ['invoice' => InvoiceResource::make($invoice->fresh())->resolve()],
            'Invoice updated.'
        );
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return ApiResponse::success([], 'Invoice deleted.');
    }

    /**
     * Per-status counts, amounts, and 6-month due-date trends for dashboard invoice widgets.
     */
    public function chartSummary(): JsonResponse
    {
        $companyId = $this->companyId();
        $now = Carbon::now();

        $statuses = [
            'pending' => InvoiceStatus::Pending,
            'overdue' => InvoiceStatus::Overdue,
            'paid' => InvoiceStatus::Paid,
            'partial' => InvoiceStatus::Partial,
        ];

        $out = [];
        foreach ($statuses as $key => $status) {
            $base = Invoice::query()->forCompany($companyId)->where('status', $status);
            $count = (clone $base)->count();
            $amount = (float) (clone $base)->sum('amount');
            $trend = [];
            for ($i = 5; $i >= 0; $i--) {
                $ms = $now->copy()->subMonths($i)->startOfMonth();
                $me = $now->copy()->subMonths($i)->endOfMonth();
                $trend[] = Invoice::query()
                    ->forCompany($companyId)
                    ->where('status', $status)
                    ->whereBetween('due_date', [$ms->toDateString(), $me->toDateString()])
                    ->count();
            }
            $out[$key] = [
                'count' => $count,
                'amount' => round($amount, 2),
                'trend' => $trend,
            ];
        }

        return ApiResponse::success($out, '');
    }

    public function generateMonthly(GenerateMonthlyInvoicesRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $year = (int) ($request->validated('year') ?? Carbon::now()->year);
        $month = (int) ($request->validated('month') ?? Carbon::now()->month);

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $dueDate = $periodStart->copy()->endOfMonth();

        $leases = Lease::query()
            ->forCompany($companyId)
            ->where('status', LeaseStatus::Active)
            ->get();

        $created = DB::transaction(function () use ($leases, $companyId, $year, $month, $dueDate): int {
            $count = 0;
            foreach ($leases as $lease) {
                $exists = Invoice::query()
                    ->forCompany($companyId)
                    ->where('lease_id', $lease->id)
                    ->whereYear('due_date', $year)
                    ->whereMonth('due_date', $month)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Invoice::query()->create([
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'amount' => $lease->rent_amount,
                    'due_date' => $dueDate->toDateString(),
                    'status' => InvoiceStatus::Pending,
                ]);

                $count++;
            }

            return $count;
        });

        return ApiResponse::success(
            [
                'created_count' => $created,
                'year' => $year,
                'month' => $month,
                'due_date' => $dueDate->toDateString(),
            ],
            sprintf('Generated %d invoice(s) for %d-%02d.', $created, $year, $month)
        );
    }
}
