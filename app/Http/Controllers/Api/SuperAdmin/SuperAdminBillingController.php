<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\CompanyStatus;
use App\Enums\SaasSubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Plan;
use App\Models\SaasSubscriptionPayment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class SuperAdminBillingController extends Controller
{
    public function index(): JsonResponse
    {
        $now = Carbon::now();

        $startMonth = $now->copy()->startOfMonth();
        $endMonth = $now->copy()->endOfMonth();
        $startYear = $now->copy()->startOfYear();

        $paidBase = SaasSubscriptionPayment::query()->where('status', SaasSubscriptionPaymentStatus::Paid);

        $revenueThisMonth = (float) (clone $paidBase)
            ->whereBetween('paid_at', [$startMonth, $endMonth])
            ->sum('amount');

        $revenueThisYear = (float) (clone $paidBase)
            ->whereBetween('paid_at', [$startYear, $now])
            ->sum('amount');

        $revenueAllTime = (float) (clone $paidBase)->sum('amount');

        $revenueByMonth = [];
        $mrrStackedByMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();
            $amount = (float) SaasSubscriptionPayment::query()
                ->where('status', SaasSubscriptionPaymentStatus::Paid)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');

            $freeAmt = (float) SaasSubscriptionPayment::query()
                ->where('status', SaasSubscriptionPaymentStatus::Paid)
                ->whereBetween('paid_at', [$start, $end])
                ->whereHas('plan', fn ($q) => $q->where('slug', 'free'))
                ->sum('amount');
            $starterAmt = (float) SaasSubscriptionPayment::query()
                ->where('status', SaasSubscriptionPaymentStatus::Paid)
                ->whereBetween('paid_at', [$start, $end])
                ->whereHas('plan', fn ($q) => $q->where('slug', 'basic'))
                ->sum('amount');
            $businessAmt = (float) SaasSubscriptionPayment::query()
                ->where('status', SaasSubscriptionPaymentStatus::Paid)
                ->whereBetween('paid_at', [$start, $end])
                ->whereHas('plan', fn ($q) => $q->where('slug', 'pro'))
                ->sum('amount');

            $revenueByMonth[] = [
                'month_key' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'amount' => round($amount, 2),
            ];

            $stackTotal = round($freeAmt + $starterAmt + $businessAmt, 2);
            $mrrStackedByMonth[] = [
                'month_key' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'short_label' => $start->format('M'),
                'free' => round($freeAmt, 2),
                'starter' => round($starterAmt, 2),
                'business' => round($businessAmt, 2),
                'enterprise' => 0.0,
                'total' => $stackTotal > 0 ? $stackTotal : round($amount, 2),
            ];
        }

        $plans = Plan::query()->whereIn('slug', ['free', 'basic', 'pro'])->get()->keyBy('slug');

        $counts = [];
        foreach (['free', 'basic', 'pro'] as $slug) {
            $plan = $plans->get($slug);
            $counts[$slug] = $plan
                ? Subscription::query()->where('plan_id', $plan->id)->count()
                : 0;
        }

        $basicPlan = $plans->get('basic');
        $proPlan = $plans->get('pro');
        $basicPrice = $basicPlan ? (float) $basicPlan->price : 29.0;
        $proPrice = $proPlan ? (float) $proPlan->price : 79.0;

        $activeCompany = fn ($q) => $q->where('status', CompanyStatus::Active);

        $mrr = (float) Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('plan', fn ($q) => $q->whereIn('slug', ['basic', 'pro']))
            ->whereHas('company', $activeCompany)
            ->with('plan')
            ->get()
            ->sum(fn (Subscription $s) => (float) ($s->plan?->price ?? 0));

        $basicPaying = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('plan', fn ($q) => $q->where('slug', 'basic'))
            ->whereHas('company', $activeCompany)
            ->count();

        $proPaying = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('plan', fn ($q) => $q->where('slug', 'pro'))
            ->whereHas('company', $activeCompany)
            ->count();

        $basicMrr = $basicPaying * $basicPrice;
        $proMrr = $proPaying * $proPrice;

        $payments = SaasSubscriptionPayment::query()
            ->with(['company', 'plan'])
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')
            ->limit(150)
            ->get()
            ->map(fn (SaasSubscriptionPayment $p) => [
                'id' => $p->id,
                'company_name' => $p->company?->name ?? '—',
                'plan' => $p->plan?->slug ?? '—',
                'amount' => round((float) $p->amount, 2),
                'currency' => strtoupper($p->currency),
                'date_paid' => $p->paid_at?->toIso8601String(),
                'due_at' => $p->due_at?->toIso8601String(),
                'status' => $p->status->value,
            ]);

        return ApiResponse::success([
            'revenue' => [
                'this_month' => round($revenueThisMonth, 2),
                'this_year' => round($revenueThisYear, 2),
                'all_time' => round($revenueAllTime, 2),
                'by_month' => $revenueByMonth,
                'mrr_stacked_by_month' => $mrrStackedByMonth,
            ],
            'breakdown' => [
                'free_count' => $counts['free'],
                'basic_count' => $counts['basic'],
                'pro_count' => $counts['pro'],
                'basic_paying_count' => $basicPaying,
                'pro_paying_count' => $proPaying,
                'basic_price' => $basicPrice,
                'pro_price' => $proPrice,
                'basic_mrr' => round($basicMrr, 2),
                'pro_mrr' => round($proMrr, 2),
                'total_mrr' => round($mrr, 2),
            ],
            'payments' => $payments,
        ], '');
    }
}
