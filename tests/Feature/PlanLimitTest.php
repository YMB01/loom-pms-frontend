<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_blocks_second_property_with_upgrade_message(): void
    {
        $free = Plan::query()->where('slug', 'free')->firstOrFail();

        $company = Company::query()->create([
            'name' => 'Limit Co',
            'email' => 'limit@example.com',
            'currency' => 'ETB',
            'status' => CompanyStatus::Active,
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $free->id,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(10),
            'current_period_start' => null,
            'current_period_end' => null,
        ]);

        $user = User::query()->create([
            'company_id' => $company->id,
            'name' => 'Admin',
            'email' => 'limit-admin@example.com',
            'password' => 'Password1!',
            'role' => UserRole::CompanyAdmin,
        ]);

        Property::query()->create([
            'company_id' => $company->id,
            'name' => 'First',
            'type' => 'Residential',
            'address' => 'A',
            'city' => 'City',
            'country' => 'Ethiopia',
            'total_units' => 0,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $response = $this->postJson('/api/properties', [
            'name' => 'Second Property',
            'type' => 'Residential',
        ], $headers);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.code', 'plan_limit_exceeded');
    }
}
