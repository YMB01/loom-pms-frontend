<?php

namespace Tests\Feature;

use Database\Seeders\LoomPmsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors manual Postman checks: login → dashboard → properties → tenants.
 * Run after configuring MySQL in .env: php artisan migrate:fresh --seed && php artisan test --filter=LocalApiSmokeTest
 */
class LocalApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LoomPmsDemoSeeder::class);
    }

    public function test_login_dashboard_properties_tenants_flow(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@loomsolutions.com',
            'password' => 'admin123',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'email', 'name', 'company'],
                ],
            ]);

        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_properties',
                    'total_units',
                    'total_tenants',
                ],
            ]);

        $this->getJson('/api/properties', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 4);

        $this->getJson('/api/tenants', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 6);
    }
}
