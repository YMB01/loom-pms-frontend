<?php

namespace Tests\Feature;

use App\Models\SystemMessage;
use Database\Seeders\LoomPmsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'super_admin.email' => 'owner@system.test',
            'super_admin.password' => 'super-secret-password',
        ]);
    }

    public function test_super_admin_broadcast_appears_in_company_inbox(): void
    {
        $this->seed(LoomPmsDemoSeeder::class);

        $sa = $this->postJson('/api/super-admin/login', [
            'email' => 'owner@system.test',
            'password' => 'super-secret-password',
        ]);
        $saToken = $sa->json('data.token');
        $h = ['Authorization' => 'Bearer '.$saToken];

        $this->postJson('/api/super-admin/messages', [
            'title' => 'System notice',
            'body' => 'Hello from super admin.',
            'type' => 'announcement',
            'sent_to' => 'all',
            'send_email' => false,
        ], $h)->assertStatus(201);

        $this->assertDatabaseHas('messages', ['title' => 'System notice']);

        $userLogin = $this->postJson('/api/auth/login', [
            'email' => 'admin@loomsolutions.com',
            'password' => 'admin123',
        ]);
        $userLogin->assertOk();
        $userToken = $userLogin->json('data.token');
        $uh = ['Authorization' => 'Bearer '.$userToken];

        $this->getJson('/api/inbox/messages', $uh)
            ->assertOk()
            ->assertJsonPath('data.messages.0.title', 'System notice');

        $this->getJson('/api/inbox/messages/unread-count', $uh)
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $messageId = SystemMessage::query()->firstOrFail()->id;

        $this->postJson("/api/inbox/messages/{$messageId}/read", [], $uh)
            ->assertOk();

        $this->getJson('/api/inbox/messages/unread-count', $uh)
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        Cache::flush();
    }
}
