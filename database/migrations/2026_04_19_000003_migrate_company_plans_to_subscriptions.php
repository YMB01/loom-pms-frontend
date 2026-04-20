<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'plan')) {
            return;
        }

        $planSlugToId = DB::table('plans')->pluck('id', 'slug')->all();

        $companies = DB::table('companies')->select('*')->get();

        foreach ($companies as $c) {
            $slug = $c->plan ?? 'free';
            $planId = $planSlugToId[$slug] ?? $planSlugToId['free'];

            $subscriptionStatus = match ($c->status ?? 'active') {
                'trial' => 'trial',
                'suspended' => 'suspended',
                default => 'active',
            };

            DB::table('subscriptions')->insert([
                'company_id' => $c->id,
                'plan_id' => $planId,
                'status' => $subscriptionStatus,
                'trial_ends_at' => $c->trial_ends_at,
                'current_period_start' => $subscriptionStatus === 'trial' ? null : now(),
                'current_period_end' => $subscriptionStatus === 'trial' ? null : now()->addMonth(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('companies')->where('status', 'trial')->update(['status' => 'active']);

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['plan', 'trial_ends_at']);
        });
    }

    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be reversed safely.');
    }
};
