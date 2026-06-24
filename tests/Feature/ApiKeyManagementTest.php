<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ApiKeyService::class);
        $this->user = User::factory()->create();
    }

    public function test_user_can_list_own_keys(): void
    {
        $this->service->create($this->user, ['name' => 'One']);
        $this->service->create($this->user, ['name' => 'Two']);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/v1/api-keys')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_see_another_users_keys(): void
    {
        $other = User::factory()->create();
        $this->service->create($other, ['name' => 'Theirs']);
        $this->service->create($this->user, ['name' => 'Mine']);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/v1/api-keys')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');
    }

    public function test_user_can_create_key(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/v1/api-keys', ['name' => 'Production server'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['key', 'message', 'api_key' => ['id', 'key_prefix']]]);
    }

    public function test_create_returns_plaintext_only_once(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/v1/api-keys', ['name' => 'Prod'])->assertCreated();
        $id = $response->json('data.api_key.id');
        $plaintext = $response->json('data.key');

        $this->assertStringStartsWith('sk_live_', $plaintext);

        // The secret never appears again — only the masked prefix does.
        $show = $this->getJson("/v1/api-keys/{$id}")->assertOk();
        $this->assertNull($show->json('data.key'));
        $this->assertStringContainsString('•', $show->json('data.key_prefix'));
    }

    public function test_user_can_update_key_name(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Old']);

        Sanctum::actingAs($this->user, ['*']);

        $this->patchJson("/v1/api-keys/{$created->model->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New');
    }

    public function test_user_cannot_update_key_environment(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod', 'environment' => 'live']);

        Sanctum::actingAs($this->user, ['*']);

        $this->patchJson("/v1/api-keys/{$created->model->id}", ['environment' => 'test'])
            ->assertOk()
            ->assertJsonPath('data.environment', 'live');
    }

    public function test_user_can_revoke_own_key(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/v1/api-keys/{$created->model->id}")->assertNoContent();

        $this->assertFalse($created->model->fresh()->is_active);
    }

    public function test_user_cannot_revoke_another_users_key(): void
    {
        $other = User::factory()->create();
        $created = $this->service->create($other, ['name' => 'Theirs']);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/v1/api-keys/{$created->model->id}")->assertStatus(403);

        $this->assertTrue($created->model->fresh()->is_active);
    }

    public function test_user_can_view_key_usage_stats(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/v1/api-keys/{$created->model->id}/usage")
            ->assertOk()
            ->assertJsonStructure(['data' => ['request_count', 'last_used_at', 'last_used_ip', 'top_endpoints', 'recent_logs']]);
    }

    public function test_max_keys_per_user_limit_is_enforced(): void
    {
        config()->set('api-keys.max_keys_per_user', 2);

        $this->service->create($this->user, ['name' => 'One']);
        $this->service->create($this->user, ['name' => 'Two']);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/v1/api-keys', ['name' => 'Three'])->assertStatus(422);
    }
}
