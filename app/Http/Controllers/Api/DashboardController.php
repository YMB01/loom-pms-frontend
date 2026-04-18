<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DashboardIndexRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use InteractsWithCompany;

    public function index(DashboardIndexRequest $request): JsonResponse
    {
        $companyId = $this->companyId();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $totalProperties = Property::query()->forCompany($companyId)->count();
        $totalUnits = Unit::query()->forCompany($companyId)->count();
        $occupiedUnits = Unit::query()->forCompany($companyId)->where('status', UnitStatus::Occupied)->count();
        $totalTenants = Tenant::query()->forCompany($companyId)->count();

        $occupancyRate = $totalUnits > 0
            ? round(($occupiedUnits / $totalUnits) * 100, 1)
            : 0.0;

        $totalRevenueThisMonth = (float) Payment::query()
            ->forCompany($companyId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $pendingInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('status', InvoiceStatus::Pending)
            ->count();

        $overdueInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('status', InvoiceStatus::Overdue)
            ->count();

        $openMaintenance = MaintenanceRequest::query()
            ->forCompany($companyId)
            ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
            ->count();

        $revenueLast6Months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            $amount = (float) Payment::query()
                ->forCompany($companyId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');
            $revenueLast6Months[] = [
                'month_key' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M Y'),
                'amount' => $amount,
            ];
        }

        $recentPayments = Payment::query()
            ->forCompany($companyId)
            ->with(['tenant', 'invoice.lease.unit'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(static function (Payment $p): array {
                return [
                    'id' => $p->id,
                    'tenant_name' => $p->tenant?->name ?? '—',
                    'unit' => $p->invoice?->lease?->unit?->unit_number ?? '—',
                    'amount' => (float) $p->amount,
                    'method' => $p->method ?? '—',
                    'reference' => $p->reference,
                    'invoice_status' => $p->invoice?->status?->value ?? 'unknown',
                    'created_at' => $p->created_at?->toIso8601String(),
                ];
            })->values()->all();

        $recentMaintenance = MaintenanceRequest::query()
            ->forCompany($companyId)
            ->with(['property'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(static function (MaintenanceRequest $m): array {
                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'unit' => $m->unit,
                    'status' => $m->status->value,
                    'priority' => $m->priority->value,
                    'property_name' => $m->property?->name ?? '—',
                    'created_at' => $m->created_at?->toIso8601String(),
                ];
            })->values()->all();

        $smsToday = SmsLog::query()
            ->forCompany($companyId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $smsFailed = SmsLog::query()
            ->forCompany($companyId)
            ->where('status', 'failed')
            ->count();

        $smsSent = SmsLog::query()
            ->forCompany($companyId)
            ->where('status', 'sent')
            ->count();

        $smsTotal = SmsLog::query()->forCompany($companyId)->count();
        $deliveryDenominator = $smsSent + $smsFailed;
        $deliveryRatePercent = $deliveryDenominator > 0
            ? round(($smsSent / $deliveryDenominator) * 100, 1)
            : ($smsTotal === 0 ? 100.0 : 100.0);

        $primaryProvider = filled(config('services.africastalking.username'))
            ? 'Africa\'s Talking'
            : 'Twilio';

        return ApiResponse::success([
            'total_properties' => $totalProperties,
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'occupancy_rate' => $occupancyRate,
            'total_tenants' => $totalTenants,
            'total_revenue_this_month' => $totalRevenueThisMonth,
            'pending_invoices_count' => $pendingInvoices,
            'overdue_invoices_count' => $overdueInvoices,
            'open_maintenance_count' => $openMaintenance,
            'revenue_last_6_months' => $revenueLast6Months,
            'recent_payments' => $recentPayments,
            'recent_maintenance' => $recentMaintenance,
            'sms' => [
                'primary_provider' => $primaryProvider,
                'sent_today' => $smsToday,
                'failed' => $smsFailed,
                'queued' => 0,
                'sent_total' => $smsSent,
                'delivery_rate_percent' => $deliveryRatePercent,
            ],
        ], '');
    }
}
