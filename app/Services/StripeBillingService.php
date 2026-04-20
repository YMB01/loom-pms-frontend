<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\SaasSubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SaasSubscriptionPayment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeBillingService
{
    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'))
            && filled(config('services.stripe.price_basic'))
            && filled(config('services.stripe.price_pro'));
    }

    public function createCheckoutSession(Company $company, string $planSlug): string
    {
        if (! in_array($planSlug, ['basic', 'pro'], true)) {
            throw new \InvalidArgumentException('Invalid plan.');
        }

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Stripe billing is not configured on the server.');
        }

        $priceId = $this->stripePriceIdForPlan($planSlug);
        if (! $priceId) {
            throw new \RuntimeException('Stripe price ID missing for this plan.');
        }

        $stripe = $this->client();
        $customerId = $this->ensureStripeCustomer($company, $stripe);

        $frontend = rtrim((string) config('services.stripe.frontend_url', 'http://localhost:3000'), '/');

        $session = $stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $company->id,
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'success_url' => $frontend.'/upgrade?checkout=success',
            'cancel_url' => $frontend.'/upgrade?checkout=cancelled',
            'metadata' => [
                'company_id' => (string) $company->id,
                'plan_slug' => $planSlug,
            ],
            'subscription_data' => [
                'metadata' => [
                    'company_id' => (string) $company->id,
                    'plan_slug' => $planSlug,
                ],
            ],
        ]);

        return $session->url ?? '';
    }

    public function createBillingPortalSession(Company $company): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Stripe billing is not configured on the server.');
        }

        $stripe = $this->client();
        $customerId = $this->ensureStripeCustomer($company, $stripe);

        $frontend = rtrim((string) config('services.stripe.frontend_url', 'http://localhost:3000'), '/');

        $session = $stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $frontend.'/upgrade',
        ]);

        return $session->url ?? '';
    }

    /**
     * @throws SignatureVerificationException
     */
    public function processWebhook(string $payload, ?string $sigHeader): void
    {
        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        if (! is_string($sigHeader) || $sigHeader === '') {
            throw new UnexpectedValueException('Missing Stripe-Signature header.');
        }

        $event = Webhook::constructEvent($payload, $sigHeader, $secret);

        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutSessionCompleted($event->data->object),
            'invoice.paid' => $this->onInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->onInvoicePaymentFailed($event->data->object),
            'invoice.updated' => $this->onInvoiceUpdated($event->data->object),
            'customer.subscription.updated' => $this->onCustomerSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->onCustomerSubscriptionDeleted($event->data->object),
            default => null,
        };
    }

    private function client(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            throw new \RuntimeException('Stripe is not configured.');
        }

        return new StripeClient($secret);
    }

    private function ensureStripeCustomer(Company $company, StripeClient $stripe): string
    {
        if ($company->stripe_customer_id) {
            return $company->stripe_customer_id;
        }

        $customer = $stripe->customers->create([
            'email' => $company->email,
            'metadata' => ['company_id' => (string) $company->id],
        ]);

        $company->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    private function stripePriceIdForPlan(string $slug): ?string
    {
        return match ($slug) {
            'basic' => config('services.stripe.price_basic'),
            'pro' => config('services.stripe.price_pro'),
            default => null,
        };
    }

    private function planSlugFromStripePriceId(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        if ($priceId === config('services.stripe.price_basic')) {
            return 'basic';
        }
        if ($priceId === config('services.stripe.price_pro')) {
            return 'pro';
        }

        return null;
    }

    private function onCheckoutSessionCompleted(object $session): void
    {
        if (($session->mode ?? '') !== 'subscription') {
            return;
        }

        $companyId = (int) ($session->metadata->company_id ?? 0);
        if ($companyId < 1) {
            return;
        }

        $company = Company::query()->with('subscription')->find($companyId);
        if (! $company || ! $company->subscription) {
            return;
        }

        $stripeSubId = $session->subscription ?? null;
        if (! is_string($stripeSubId)) {
            return;
        }

        $stripe = $this->client();
        $sub = $stripe->subscriptions->retrieve($stripeSubId, ['expand' => ['items.data.price']]);
        $priceId = $sub->items->data[0]->price->id ?? null;
        $slug = $this->planSlugFromStripePriceId($priceId)
            ?? (is_string($session->metadata->plan_slug ?? null) ? $session->metadata->plan_slug : null);

        if (! is_string($slug)) {
            return;
        }

        $plan = Plan::query()->where('slug', $slug)->first();
        if (! $plan) {
            return;
        }

        DB::transaction(function () use ($company, $plan, $sub, $priceId, $stripeSubId, $session): void {
            $company->update([
                'stripe_customer_id' => is_string($session->customer) ? $session->customer : $company->stripe_customer_id,
                'status' => CompanyStatus::Active,
            ]);

            $company->subscription->update([
                'plan_id' => $plan->id,
                'stripe_subscription_id' => $stripeSubId,
                'stripe_price_id' => is_string($priceId) ? $priceId : null,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => null,
                'current_period_start' => Carbon::createFromTimestamp((int) $sub->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp((int) $sub->current_period_end),
            ]);
        });
    }

    private function onInvoicePaid(object $invoice): void
    {
        $company = Company::query()->where('stripe_customer_id', $invoice->customer)->first();
        if (! $company) {
            return;
        }

        $company->load('subscription.plan');
        $plan = $company->subscription?->plan;

        $paidAt = now();
        if (
            isset($invoice->status_transitions)
            && is_object($invoice->status_transitions)
            && ! empty($invoice->status_transitions->paid_at)
        ) {
            $paidAt = Carbon::createFromTimestamp((int) $invoice->status_transitions->paid_at);
        }

        $amount = ((int) ($invoice->amount_paid ?? 0)) / 100;

        SaasSubscriptionPayment::query()->updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'company_id' => $company->id,
                'plan_id' => $plan?->id,
                'amount' => $amount,
                'currency' => strtoupper((string) ($invoice->currency ?? 'usd')),
                'status' => SaasSubscriptionPaymentStatus::Paid,
                'paid_at' => $paidAt,
                'due_at' => null,
                'description' => $invoice->description ?? 'Subscription',
            ]
        );
    }

    private function onInvoicePaymentFailed(object $invoice): void
    {
        $company = Company::query()->where('stripe_customer_id', $invoice->customer)->first();
        if (! $company) {
            return;
        }

        $company->load('subscription.plan');
        $plan = $company->subscription?->plan;

        $amount = ((int) ($invoice->amount_due ?? $invoice->total ?? 0)) / 100;

        SaasSubscriptionPayment::query()->updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'company_id' => $company->id,
                'plan_id' => $plan?->id,
                'amount' => $amount,
                'currency' => strtoupper((string) ($invoice->currency ?? 'usd')),
                'status' => SaasSubscriptionPaymentStatus::Failed,
                'paid_at' => null,
                'due_at' => isset($invoice->due_date) && $invoice->due_date
                    ? Carbon::createFromTimestamp((int) $invoice->due_date)
                    : null,
                'description' => $invoice->description ?? 'Subscription payment failed',
            ]
        );
    }

    private function onInvoiceUpdated(object $invoice): void
    {
        if (($invoice->status ?? '') !== 'open') {
            return;
        }

        $due = isset($invoice->due_date) && $invoice->due_date
            ? Carbon::createFromTimestamp((int) $invoice->due_date)
            : null;

        if (! $due || ! $due->isPast()) {
            return;
        }

        $company = Company::query()->where('stripe_customer_id', $invoice->customer)->first();
        if (! $company) {
            return;
        }

        $company->load('subscription.plan');
        $plan = $company->subscription?->plan;

        $amount = ((int) ($invoice->amount_due ?? 0)) / 100;

        SaasSubscriptionPayment::query()->updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'company_id' => $company->id,
                'plan_id' => $plan?->id,
                'amount' => $amount,
                'currency' => strtoupper((string) ($invoice->currency ?? 'usd')),
                'status' => SaasSubscriptionPaymentStatus::Overdue,
                'paid_at' => null,
                'due_at' => $due,
                'description' => $invoice->description ?? 'Subscription overdue',
            ]
        );
    }

    private function onCustomerSubscriptionUpdated(object $stripeSub): void
    {
        $local = Subscription::query()->where('stripe_subscription_id', $stripeSub->id)->first();
        if (! $local) {
            return;
        }

        $status = (string) ($stripeSub->status ?? '');

        match ($status) {
            'active', 'trialing' => $this->activateLocalSubscription($local),
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => $this->suspendLocalForPayment($local),
            'canceled' => $this->downgradeLocalToFree($local),
            default => null,
        };
    }

    private function onCustomerSubscriptionDeleted(object $stripeSub): void
    {
        $local = Subscription::query()->where('stripe_subscription_id', $stripeSub->id)->first();
        if (! $local) {
            return;
        }

        $this->downgradeLocalToFree($local);
    }

    private function activateLocalSubscription(Subscription $local): void
    {
        DB::transaction(function () use ($local): void {
            $local->company?->update(['status' => CompanyStatus::Active]);
            $local->update(['status' => SubscriptionStatus::Active]);
        });
    }

    private function suspendLocalForPayment(Subscription $local): void
    {
        DB::transaction(function () use ($local): void {
            $local->company?->update(['status' => CompanyStatus::Suspended]);
            $local->update(['status' => SubscriptionStatus::Suspended]);
        });
    }

    private function downgradeLocalToFree(Subscription $local): void
    {
        $free = Plan::query()->where('slug', 'free')->first();
        if (! $free) {
            return;
        }

        DB::transaction(function () use ($local, $free): void {
            $local->company?->update(['status' => CompanyStatus::Active]);
            $local->update([
                'plan_id' => $free->id,
                'stripe_subscription_id' => null,
                'stripe_price_id' => null,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => null,
                'current_period_start' => null,
                'current_period_end' => null,
            ]);
        });
    }
}
