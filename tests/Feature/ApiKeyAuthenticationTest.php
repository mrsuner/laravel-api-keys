<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mrsuner\ApiKeys\Jobs\TrackApiKeyUsage;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
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

    public function test_request_with_valid_api_key_authenticates_user(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $this->withToken($created->plaintext)
            ->getJson('/test/me')
            ->assertOk()
            ->assertJson(['user_id' => $this->user->getKey()]);
    }

    public function test_request_with_invalid_api_key_returns_401(): void
    {
        $this->withToken('sk_live_'.str_repeat('A', 24))
            ->getJson('/test/me')
            ->assertStatus(401);
    }

    public function test_request_with_revoked_key_returns_401(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $this->service->revoke($created->model);

        $this->withToken($created->plaintext)
            ->getJson('/test/me')
            ->assertStatus(401);
    }

    public function test_request_with_expired_key_returns_401(): void
    {
        $created = $this->service->create($this->user, [
            'name'       => 'Prod',
            'expires_at' => now()->subDay(),
        ]);

        $this->withToken($created->plaintext)
            ->getJson('/test/me')
            ->assertStatus(401);
    }

    public function test_request_in_overlap_window_succeeds(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $this->service->rotate($created->model, now()->addHour());

        $this->withToken($created->plaintext)
            ->getJson('/test/me')
            ->assertOk();
    }

    public function test_request_with_sanctum_token_bypasses_api_key_middleware(): void
    {
        $token = $this->user->createToken('cli')->plainTextToken;

        $this->withToken($token)
            ->getJson('/test/me')
            ->assertOk()
            ->assertJson(['user_id' => $this->user->getKey()]);
    }

    public function test_ability_middleware_blocks_insufficient_key(): void
    {
        $created = $this->service->create($this->user, [
            'name'      => 'Limited',
            'abilities' => ['read:users'],
        ]);

        $this->withToken($created->plaintext)
            ->getJson('/test/orders')
            ->assertStatus(403);
    }

    public function test_ability_middleware_allows_superkey(): void
    {
        $created = $this->service->create($this->user, [
            'name'      => 'Super',
            'abilities' => null,
        ]);

        $this->withToken($created->plaintext)
            ->getJson('/test/orders')
            ->assertOk();
    }

    public function test_usage_tracking_job_is_dispatched(): void
    {
        Queue::fake();

        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();

        Queue::assertPushed(TrackApiKeyUsage::class, function (TrackApiKeyUsage $job) use ($created) {
            return $job->apiKeyId === $created->model->id
                && $job->endpoint === '/test/me'
                && $job->statusCode === 200;
        });
    }

    public function test_usage_tracking_updates_counters(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        // Sync queue: the terminate() job runs after the response.
        $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();

        $fresh = $created->model->fresh();
        $this->assertSame(1, $fresh->request_count);
        $this->assertNotNull($fresh->last_used_at);
    }
}
