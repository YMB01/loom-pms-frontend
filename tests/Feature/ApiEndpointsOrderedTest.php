<?php

namespace Tests\Feature;

use Database\Seeders\LoomPmsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiEndpointsOrderedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Runs the eight API checks in order and prints JSON for each response (see phpunit stdout).
     */
    public function test_loom_pms_api_endpoints_in_order_and_print_responses(): void
    {
        $this->seed(LoomPmsDemoSeeder::class);

        $registerEmail = 'apitest_'.uniqid('', true).'@example.com';

        $r1 = $this->postJson('/api/auth/register', [
            'company_name' => 'API Test Company',
            'email' => $registerEmail,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'phone' => '+10000000001',
        ]);
        $this->printStep('1. POST /api/auth/register', $r1);
        $r1->assertStatus(201);

        $r2 = $this->postJson('/api/auth/login', [
            'email' => 'admin@loomsolutions.com',
            'password' => 'admin123',
        ]);
        $this->printStep('2. POST /api/auth/login (admin@loomsolutions.com)', $r2);
        $r2->assertOk();
        $r2->assertJsonPath('success', true);

        $token = $r2->json('data.token');
        $this->assertNotEmpty($token, 'Login response must include data.token');

        $auth = ['Authorization' => 'Bearer '.$token];

        $r3 = $this->withHeaders($auth)->getJson('/api/dashboard');
        $this->printStep('3. GET /api/dashboard', $r3);
        $r3->assertOk();

        $r4 = $this->withHeaders($auth)->getJson('/api/properties');
        $this->printStep('4. GET /api/properties', $r4);
        $r4->assertOk();

        $r5 = $this->withHeaders($auth)->getJson('/api/tenants');
        $this->printStep('5. GET /api/tenants', $r5);
        $r5->assertOk();

        $r6 = $this->withHeaders($auth)->getJson('/api/invoices');
        $this->printStep('6. GET /api/invoices', $r6);
        $r6->assertOk();

        $r7 = $this->withHeaders($auth)->getJson('/api/payments');
        $this->printStep('7. GET /api/payments', $r7);
        $r7->assertOk();

        $r8 = $this->withHeaders($auth)->getJson('/api/maintenance');
        $this->printStep('8. GET /api/maintenance', $r8);
        $r8->assertOk();
    }

    private function printStep(string $label, TestResponse $response): void
    {
        $decoded = $response->json();
        $body = $decoded !== null
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $response->getContent();

        fwrite(STDOUT, "\n".str_repeat('=', 72)."\n".$label."\nHTTP ".$response->status()."\n".$body."\n");
    }
}
