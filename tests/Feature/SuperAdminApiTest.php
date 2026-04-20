<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use Database\Seeders\LoomPmsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SuperAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'super_admin.email' => 'owner@system.test',
            'super_admin.password' => 'super-secret-password',
            'super_admin.token_ttl_hours' => 8,
        ]);
    }

    public function test_super_admin_login_and_dashboard_are_isolated_from_sanctum(): void
    {
        $this->seed(LoomPmsDemoSeeder::class);

        $bad = $this->postJson('/api/super-admin/login', [
            'email' => 'owner@system.test',
            'password' => 'wrong',
        ]);
        $bad->assertStatus(401);

        $login = $this->postJson('/api/super-admin/login', [
            'email' => 'owner@system.test',
            'password' => 'super-secret-password',
        ]);
        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_in_hours']]);

        $saToken = $login->json('data.token');
        $this->assertNotEmpty($saToken);

        $headers = ['Authorization' => 'Bearer '.$saToken];

        $this->getJson('/api/super-admin/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.role', 'super_admin')
            ->assertJsonPath('data.email', 'owner@system.test');

        $this->getJson('/api/super-admin/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_companies',
                    'active_companies',
                    'suspended_companies',
                    'trial_companies',
                    'mrr',
                    'arr',
                    'revenue_by_plan',
                    'revenue_chart',
                    'recent_companies',
                    'marketplace_orders_today',
                    'marketplace_revenue_today',
                    'system_health' => ['database', 'sms', 'storage', 'queue'],
                ],
            ]);

        $this->getJson('/api/super-admin/companies', $headers)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $userLogin = $this->postJson('/api/auth/login', [
            'email' => 'admin@loomsolutions.com',
            'password' => 'admin123',
        ]);
        $userLogin->assertOk();
        $userToken = $userLogin->json('data.token');

        $this->getJson('/api/super-admin/dashboard', [
            'Authorization' => 'Bearer '.$userToken,
        ])->assertStatus(401);

        $this->postJson('/api/super-admin/logout', [], $headers)->assertOk();
        Cache::flush();
    }

    public function test_super_admin_can_activate_suspend_and_delete_company_without_tenant_payload(): void
    {
        $company = Company::query()->create([
            'name' => 'SA Co',
            'email' => 'sa@example.com',
            'currency' => 'ETB',
            'status' => CompanyStatus::Active,
        ]);

        $login = $this->postJson('/api/super-admin/login', [
            'email' => 'owner@system.test',
            'password' => 'super-secret-password',
        ]);
        $token = $login->json('data.token');
        $h = ['Authorization' => 'Bearer '.$token];

        $this->patchJson("/api/super-admin/companies/{$company->id}/suspend", [], $h)
            ->assertOk()
            ->assertJsonPath('data.company.account_status', 'suspended');

        $this->patchJson("/api/super-admin/companies/{$company->id}/activate", [], $h)
            ->assertOk()
            ->assertJsonPath('data.company.account_status', 'active');

        $this->deleteJson("/api/super-admin/companies/{$company->id}", [], $h)
            ->assertOk();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_super_admin_can_change_company_plan(): void
    {
        $company = Company::query()->create([
            'name' => 'Plan Co',
            'email' => 'plan@example.com',
            'currency' => 'ETB',
            'status' => CompanyStatus::Active,
        ]);

        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $free->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();

        $login = $this->postJson('/api/super-admin/login', [
            'email' => 'owner@system.test',
            'password' => 'super-secret-password',
        ]);
        $token = $login->json('data.token');
        $h = ['Authorization' => 'Bearer '.$token];

        $this->patchJson("/api/super-admin/companies/{$company->id}/subscription", [
            'plan_id' => $basic->id,
        ], $h)
            ->assertOk()
            ->assertJsonPath('data.company.plan', 'basic');
    }
}
