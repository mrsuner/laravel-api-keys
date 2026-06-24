<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class ApiKeyRotationTest extends TestCase
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

    public function test_rotation_creates_new_key(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson("/v1/api-keys/{$created->model->id}/rotate")->assertCreated();

        $this->assertNotSame($created->model->id, $response->json('data.api_key.id'));
    }

    public function test_rotation_returns_new_plaintext_key_once(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/v1/api-keys/{$created->model->id}/rotate")
            ->assertCreated()
            ->assertJsonStructure(['data' => ['key', 'message', 'api_key']]);
    }

    public function test_revoked_key_cannot_be_rotated(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $this->service->revoke($created->model, 'admin_revoked');

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/v1/api-keys/{$created->model->id}/rotate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only active API keys can be rotated.');

        $this->assertSame(1, ApiKey::query()->count());
    }

    public function test_expired_key_cannot_be_rotated(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $created->model->forceFill(['expires_at' => now()->subMinute()])->save();

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/v1/api-keys/{$created->model->id}/rotate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only active API keys can be rotated.');

        $this->assertSame(1, ApiKey::query()->count());
    }

    public function test_rotation_without_overlap_immediately_invalidates_old_key(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $oldPlaintext = $created->plaintext;

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson("/v1/api-keys/{$created->model->id}/rotate")->assertCreated();

        // Old key no longer authenticates.
        $this->withToken($oldPlaintext)->getJson('/test/me')->assertStatus(401);
    }

    public function test_rotation_with_overlap_keeps_old_key_valid_during_window(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $oldPlaintext = $created->plaintext;

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson("/v1/api-keys/{$created->model->id}/rotate", ['overlap_minutes' => 60])
            ->assertCreated();

        $this->withToken($oldPlaintext)->getJson('/test/me')->assertOk();
    }

    public function test_old_key_fails_after_overlap_expires(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $oldPlaintext = $created->plaintext;

        Sanctum::actingAs($this->user, ['*']);
        $this->postJson("/v1/api-keys/{$created->model->id}/rotate", ['overlap_minutes' => 60])
            ->assertCreated();

        $this->travel(61)->minutes();

        $this->withToken($oldPlaintext)->getJson('/test/me')->assertStatus(401);
    }
}
