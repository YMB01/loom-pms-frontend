<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SmsLog;
use App\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    use InteractsWithCompany;

    private function companyHeader(Request $request): array
    {
        $request->user()->loadMissing('company');
        $c = $request->user()->company;

        return [
            'name' => $c?->name ?? 'Company',
            'logo' => $c?->logo,
        ];
    }

    public function occupancy(Request $request): Response
    {
        $request->validate([
            'property_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $companyId = $this->companyId();
        $month = $request->query('month') ?? Carbon::now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $q = Unit::query()->forCompany($companyId)->with('property');
        if ($request->filled('property_id')) {
            $q->where('property_id', (int) $request->query('property_id'));
        }

        $units = $q->get();
        $occ = $units->where('status', UnitStatus::Occupied)->count();
        $vac = $units->where('status', UnitStatus::Available)->count();
        $total = $units->count();

        $h = $this->companyHeader($request);
        $html = view('reports.simple_pdf', [
            'title' => 'Occupancy report',
            'subtitle' => $start->format('F Y').($request->filled('property_id') ? ' — Property filter' : ''),
            'company' => $h,
            'summary' => [
                'Total units' => $total,
                'Occupied' => $occ,
                'Vacant' => $vac,
                'Occupancy %' => $total > 0 ? round($occ / $total * 100, 1) : 0,
            ],
            'rows' => $units->map(fn ($u) => [
                'Property' => $u->property?->name,
                'Unit' => $u->unit_number,
                'Status' => $u->status->value,
            ])->all(),
        ])->render();

        return Pdf::loadHTML($html)->stream('occupancy.pdf');
    }

    public function revenue(Request $request): Response
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $companyId = $this->companyId();
        $month = (int) ($request->query('month') ?? Carbon::now()->month);
        $year = (int) ($request->query('year') ?? Carbon::now()->year);

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $payments = Payment::query()
            ->forCompany($companyId)
            ->whereBetween('created_at', [$start, $end])
            ->with(['tenant', 'invoice'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $total = (float) $payments->sum('amount');

        $h = $this->companyHeader($request);
        $html = view('reports.simple_pdf', [
            'title' => 'Revenue report',
            'subtitle' => $start->format('F Y'),
            'company' => $h,
            'summary' => ['Total collected' => number_format($total, 2)],
            'rows' => $payments->map(fn ($p) => [
                'Date' => $p->created_at?->toDateTimeString(),
                'Tenant' => $p->tenant?->name,
                'Amount' => (string) $p->amount,
                'Method' => $p->method,
            ])->all(),
        ])->render();

        return Pdf::loadHTML($html)->stream('revenue.pdf');
    }

    public function overdue(Request $request): Response
    {
        $companyId = $this->companyId();

        $invoices = Invoice::query()
            ->forCompany($companyId)
            ->where('status', InvoiceStatus::Overdue)
            ->with('tenant')
            ->orderBy('due_date')
            ->get();

        $h = $this->companyHeader($request);
        $html = view('reports.simple_pdf', [
            'title' => 'Overdue tenants',
            'subtitle' => Carbon::now()->toDateString(),
            'company' => $h,
            'summary' => ['Overdue invoices' => $invoices->count()],
            'rows' => $invoices->map(fn ($i) => [
                'Invoice #' => $i->id,
                'Tenant' => $i->tenant?->name,
                'Amount' => (string) $i->amount,
                'Due' => $i->due_date?->toDateString(),
            ])->all(),
        ])->render();

        return Pdf::loadHTML($html)->stream('overdue.pdf');
    }

    public function maintenanceReport(Request $request): Response
    {
        $request->validate([
            'property_id' => ['nullable', 'integer'],
        ]);

        $companyId = $this->companyId();
        $q = MaintenanceRequest::query()->forCompany($companyId)->with('property');
        if ($request->filled('property_id')) {
            $q->where('property_id', (int) $request->query('property_id'));
        }

        $rows = $q->orderByDesc('created_at')->limit(500)->get();

        $h = $this->companyHeader($request);
        $html = view('reports.simple_pdf', [
            'title' => 'Maintenance report',
            'subtitle' => '',
            'company' => $h,
            'summary' => ['Requests' => $rows->count()],
            'rows' => $rows->map(fn ($m) => [
                'Title' => $m->title,
                'Property' => $m->property?->name,
                'Status' => $m->status->value,
                'Priority' => $m->priority->value,
            ])->all(),
        ])->render();

        return Pdf::loadHTML($html)->stream('maintenance.pdf');
    }

    public function leases(Request $request): Response
    {
        $companyId = $this->companyId();

        $leases = \App\Models\Lease::query()
            ->forCompany($companyId)
            ->with(['tenant', 'unit.property'])
            ->orderBy('end_date')
            ->limit(500)
            ->get();

        $h = $this->companyHeader($request);
        $html = view('reports.simple_pdf', [
            'title' => 'Lease expiry report',
            'subtitle' => '',
            'company' => $h,
            'summary' => ['Leases' => $leases->count()],
            'rows' => $leases->map(fn ($l) => [
                'Tenant' => $l->tenant?->name,
                'Unit' => $l->unit?->unit_number,
                'End date' => $l->end_date?->toDateString(),
                'Status' => $l->status->value,
            ])->all(),
        ])->render();

        return Pdf::loadHTML($html)->stream('leases.pdf');
    }

    public function sms(Request $request): Response
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $companyId = $this->companyId();
        $month = (int) ($request->query('month') ?? Carbon::now()->month);
        $year = (int) ($request->query('year') ?? Carbon::now()->year);
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $logs = SmsLog::query()
            ->forCompany($companyId)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $cost = (float) $logs->sum(fn ($l) => (float) ($l->cost ?? 0));

        $h = $this->companyHeader($request);
        $html = view('reports.simple_pdf', [
            'title' => 'SMS usage',
            'subtitle' => $start->format('F Y'),
            'company' => $h,
            'summary' => [
                'Messages' => $logs->count(),
                'Est. cost' => number_format($cost, 4),
            ],
            'rows' => $logs->map(fn ($s) => [
                'To' => $s->to_number,
                'Status' => $s->status,
                'Provider' => $s->provider,
                'When' => $s->created_at?->toDateTimeString(),
            ])->all(),
        ])->render();

        return Pdf::loadHTML($html)->stream('sms.pdf');
    }
}
