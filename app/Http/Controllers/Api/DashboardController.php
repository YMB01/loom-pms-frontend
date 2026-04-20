<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DashboardIndexRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\Lease;
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
        $userId = $request->user()->id;

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endLastMonth = $now->copy()->subMonth()->endOfMonth();

        $totalProperties = Property::query()->forCompany($companyId)->count();
        $totalUnits = Unit::query()->forCompany($companyId)->count();
        $occupiedUnits = Unit::query()->forCompany($companyId)->where('status', UnitStatus::Occupied)->count();
        $vacantUnits = Unit::query()->forCompany($companyId)->where('status', UnitStatus::Available)->count();

        $occupancyRate = $totalUnits > 0
            ? round(($occupiedUnits / $totalUnits) * 100, 1)
            : 0.0;

        $totalTenantsActive = Tenant::query()
            ->forCompany($companyId)
            ->whereHas('leases', fn ($q) => $q->where('status', LeaseStatus::Active))
            ->count();

        $revenueThisMonth = (float) Payment::query()
            ->forCompany($companyId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $revenueLastMonth = (float) Payment::query()
            ->forCompany($companyId)
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->sum('amount');

        $revenueGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100.0 : 0.0);

        $pendingInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('status', InvoiceStatus::Pending);

        $pendingInvoicesCount = (clone $pendingInvoices)->count();
        $pendingInvoicesAmount = (float) (clone $pendingInvoices)->sum('amount');

        $overdueInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('status', InvoiceStatus::Overdue);

        $overdueInvoicesCount = (clone $overdueInvoices)->count();
        $overdueInvoicesAmount = (float) (clone $overdueInvoices)->sum('amount');

        $openMaintenance = MaintenanceRequest::query()
            ->forCompany($companyId)
            ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
            ->count();

        $urgentMaintenance = MaintenanceRequest::query()
            ->forCompany($companyId)
            ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
            ->where('priority', MaintenancePriority::Urgent)
            ->count();

        $maintenanceUnits = Unit::query()->forCompany($companyId)->where('status', UnitStatus::Maintenance)->count();

        $revenueSince = $now->copy()->subMonths(12)->startOfMonth();
        $revenueByProperty = Payment::query()
            ->forCompany($companyId)
            ->where('payments.created_at', '>=', $revenueSince)
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('leases', 'leases.id', '=', 'invoices.lease_id')
            ->join('units', 'units.id', '=', 'leases.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where('properties.company_id', $companyId)
            ->groupBy('properties.id', 'properties.name', 'properties.type')
            ->selectRaw('properties.id as property_id, properties.name as property_name, properties.type as property_type, ROUND(SUM(payments.amount), 2) as revenue')
            ->orderByDesc(\Illuminate\Support\Facades\DB::raw('SUM(payments.amount)'))
            ->limit(25)
            ->get()
            ->map(static fn ($row): array => [
                'property_id' => (int) $row->property_id,
                'name' => $row->property_name ?? '—',
                'type' => $row->property_type ?? '',
                'revenue' => (float) $row->revenue,
            ])->values()->all();

        $paymentMethodBreakdownRaw = Payment::query()
            ->forCompany($companyId)
            ->where('created_at', '>=', $revenueSince)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->get();

        $paymentMethodTotal = (float) $paymentMethodBreakdownRaw->sum('total');
        $paymentMethodBreakdown = $paymentMethodBreakdownRaw->map(static function ($row) use ($paymentMethodTotal): array {
            $t = (float) $row->total;
            $pct = $paymentMethodTotal > 0 ? round(($t / $paymentMethodTotal) * 100, 1) : 0.0;
            $m = isset($row->method) && $row->method !== null && trim((string) $row->method) !== ''
                ? trim((string) $row->method)
                : 'unknown';

            return [
                'method' => $m,
                'amount' => round($t, 2),
                'percent' => $pct,
            ];
        })->values()->all();

        $revenueChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $now->copy()->subMonths($i)->endOfMonth();
            $expected = (float) Invoice::query()
                ->forCompany($companyId)
                ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount');
            $collected = (float) Payment::query()
                ->forCompany($companyId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');
            $revenueChart[] = [
                'month' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M'),
                'short_label' => $monthStart->format('M'),
                'expected' => round($expected, 2),
                'collected' => round($collected, 2),
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

        $expiringLeases = Lease::query()
            ->forCompany($companyId)
            ->where('status', LeaseStatus::Active)
            ->whereBetween('end_date', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()])
            ->with(['tenant', 'unit.property'])
            ->orderBy('end_date')
            ->limit(20)
            ->get()
            ->map(static function (Lease $l): array {
                return [
                    'id' => $l->id,
                    'end_date' => $l->end_date?->toDateString(),
                    'tenant_name' => $l->tenant?->name ?? '—',
                    'unit' => $l->unit?->unit_number ?? '—',
                    'property' => $l->unit?->property?->name ?? '—',
                ];
            })->values()->all();

        $smsThisMonth = SmsLog::query()
            ->forCompany($companyId)
            ->where('status', 'sent')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $unreadNotifications = InAppNotification::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        $primaryProvider = filled(config('services.africastalking.username'))
            ? 'Africa\'s Talking'
            : 'Twilio';

        return ApiResponse::success([
            'total_properties' => $totalProperties,
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'vacant_units' => $vacantUnits,
            'units_in_maintenance' => $maintenanceUnits,
            'occupancy_rate' => $occupancyRate,
            'total_tenants' => $totalTenantsActive,
            'revenue_this_month' => round($revenueThisMonth, 2),
            'revenue_last_month' => round($revenueLastMonth, 2),
            'revenue_growth' => $revenueGrowth,
            'pending_invoices_count' => $pendingInvoicesCount,
            'pending_invoices_amount' => round($pendingInvoicesAmount, 2),
            'overdue_invoices_count' => $overdueInvoicesCount,
            'overdue_invoices_amount' => round($overdueInvoicesAmount, 2),
            'open_maintenance_count' => $openMaintenance,
            'urgent_maintenance_count' => $urgentMaintenance,
            'revenue_chart' => $revenueChart,
            'revenue_by_property' => $revenueByProperty,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'recent_payments' => $recentPayments,
            'recent_maintenance' => $recentMaintenance,
            'expiring_leases' => $expiringLeases,
            'sms_this_month' => $smsThisMonth,
            'unread_notifications' => $unreadNotifications,
            'sms' => [
                'primary_provider' => $primaryProvider,
                'sent_today' => SmsLog::query()->forCompany($companyId)->whereDate('created_at', Carbon::today())->count(),
                'failed' => SmsLog::query()->forCompany($companyId)->where('status', 'failed')->count(),
                'queued' => 0,
                'sent_total' => SmsLog::query()->forCompany($companyId)->where('status', 'sent')->count(),
                'delivery_rate_percent' => 100.0,
            ],
        ], '');
    }
}
