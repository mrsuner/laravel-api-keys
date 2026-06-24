<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class AdminApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $service;

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ApiKeyService::class);
        $this->admin = User::factory()->create();
        $this->owner = User::factory()->create();
    }

    public function test_admin_can_list_all_api_keys(): void
    {
        $this->service->create($this->owner, ['name' => 'Owner key']);
        $this->service->create($this->admin, ['name' => 'Admin key']);

        Sanctum::actingAs($this->admin, ['admin']);

        $this->getJson('/internal/admin/v1/api-keys')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_key_detail_with_usage_stats(): void
    {
        $created = $this->service->create($this->owner, ['name' => 'Owner key']);

        Sanctum::actingAs($this->admin, ['admin']);

        $this->getJson("/internal/admin/v1/api-keys/{$created->model->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['api_key' => ['id', 'key_prefix'], 'usage' => ['request_count', 'top_endpoints', 'recent_logs']]]);
    }

    public function test_admin_can_revoke_any_users_key(): void
    {
        $created = $this->service->create($this->owner, ['name' => 'Owner key']);

        Sanctum::actingAs($this->admin, ['admin']);

        $this->deleteJson("/internal/admin/v1/api-keys/{$created->model->id}")->assertNoContent();

        $this->assertFalse($created->model->fresh()->is_active);
    }

    public function test_admin_cannot_see_key_hash(): void
    {
        $created = $this->service->create($this->owner, ['name' => 'Owner key']);

        Sanctum::actingAs($this->admin, ['admin']);

        $response = $this->getJson("/internal/admin/v1/api-keys/{$created->model->id}")->assertOk();

        $this->assertStringNotContainsString('key_hash', $response->getContent());
        $this->assertStringNotContainsString($created->model->key_hash, $response->getContent());
    }

    public function test_non_admin_cannot_access_admin_api_key_endpoints(): void
    {
        Sanctum::actingAs($this->owner, ['read']);

        $this->getJson('/internal/admin/v1/api-keys')->assertStatus(403);
    }

    public function test_request_outside_ip_whitelist_is_rejected(): void
    {
        config()->set('boilerplate.admin.ip_whitelist.enabled', true);
        config()->set('boilerplate.admin.ip_whitelist.cidrs', ['10.0.0.0/8']);

        Sanctum::actingAs($this->admin, ['admin']);

        // Test client IP (127.0.0.1) is outside the whitelist.
        $this->getJson('/internal/admin/v1/api-keys')->assertStatus(403);
    }
}
