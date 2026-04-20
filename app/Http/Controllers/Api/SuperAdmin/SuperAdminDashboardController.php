<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\CompanyStatus;
use App\Enums\SaasSubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\MarketplaceOrder;
use App\Models\Property;
use App\Models\SaasSubscriptionPayment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SuperAdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $now = Carbon::now();

        $totalCompanies = Company::query()->count();
        $activeCompanies = Company::query()->where('status', CompanyStatus::Active)->count();
        $suspendedCompanies = Company::query()->where('status', CompanyStatus::Suspended)->count();

        $trialCompanies = Subscription::query()
            ->where('status', SubscriptionStatus::Trial)
            ->count();

        $newSignupsToday = Company::query()->whereDate('created_at', $now->toDateString())->count();
        $newSignupsThisWeek = Company::query()->where('created_at', '>=', $now->copy()->subDays(7))->count();

        $totalPropertiesAcrossAll = Property::query()->count();
        $totalUnitsAcrossAll = Unit::query()->count();
        $totalTenantsAcrossAll = Tenant::query()->count();

        $mrr = (float) Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('plan', fn ($q) => $q->whereIn('slug', ['basic', 'pro']))
            ->whereHas('company', fn ($q) => $q->where('status', CompanyStatus::Active))
            ->with('plan')
            ->get()
            ->sum(fn (Subscription $s) => (float) ($s->plan?->price ?? 0));

        $arr = round($mrr * 12, 2);

        $revenueByPlan = [
            'free' => Subscription::query()->whereHas('plan', fn ($q) => $q->where('slug', 'free'))->count(),
            'starter' => Subscription::query()->whereHas('plan', fn ($q) => $q->where('slug', 'basic'))->count(),
            'business' => Subscription::query()->whereHas('plan', fn ($q) => $q->where('slug', 'pro'))->count(),
            'enterprise' => 0,
        ];

        $revenueChart = [];
        $companyGrowth = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();
            $amount = (float) SaasSubscriptionPayment::query()
                ->where('status', SaasSubscriptionPaymentStatus::Paid)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');
            $revenueChart[] = [
                'month_key' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'amount' => round($amount, 2),
            ];

            $newCompanies = Company::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();
            $cumulativeToDate = Company::query()
                ->where('created_at', '<=', $end)
                ->count();
            $companyGrowth[] = [
                'month_key' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'short_label' => $start->format('M'),
                'new_signups' => $newCompanies,
                'cumulative_companies' => $cumulativeToDate,
            ];
        }

        $recentCompanies = Company::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        $marketplaceOrdersToday = MarketplaceOrder::query()
            ->whereDate('created_at', $now->toDateString())
            ->count();

        $marketplaceRevenueToday = (float) MarketplaceOrder::query()
            ->whereDate('created_at', $now->toDateString())
            ->whereIn('status', ['completed', 'confirmed', 'in_progress'])
            ->sum('total_price');

        $databaseOk = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $databaseOk = false;
        }

        $smsOk = filled(config('services.africastalking.username'))
            || (filled(config('services.twilio.sid')) && filled(config('services.twilio.token')));

        $storageOk = true;
        try {
            Storage::disk('local')->exists('.gitignore');
        } catch (\Throwable) {
            $storageOk = false;
        }

        $queueOk = config('queue.default') !== 'sync'
            || app()->environment('local');

        return ApiResponse::success([
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'suspended_companies' => $suspendedCompanies,
            'trial_companies' => $trialCompanies,
            'new_signups_today' => $newSignupsToday,
            'new_signups_this_week' => $newSignupsThisWeek,
            'total_properties_across_all' => $totalPropertiesAcrossAll,
            'total_units_across_all' => $totalUnitsAcrossAll,
            'total_tenants_across_all' => $totalTenantsAcrossAll,
            'mrr' => round($mrr, 2),
            'arr' => $arr,
            'revenue_by_plan' => $revenueByPlan,
            'revenue_chart' => $revenueChart,
            'company_signups_by_month' => $companyGrowth,
            'recent_companies' => $recentCompanies,
            'marketplace_orders_today' => $marketplaceOrdersToday,
            'marketplace_revenue_today' => round($marketplaceRevenueToday, 2),
            'system_health' => [
                'database' => $databaseOk ? 'ok' : 'error',
                'sms' => $smsOk ? 'ok' : 'not_configured',
                'storage' => $storageOk ? 'ok' : 'error',
                'queue' => $queueOk ? 'ok' : 'sync',
            ],
        ], '');
    }
}
