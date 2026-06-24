<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class ApiKeyRateLimitTest extends TestCase
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

    public function test_requests_within_limit_succeed(): void
    {
        $created = $this->service->create($this->user, [
            'name'       => 'Prod',
            'rate_limit' => ['max_attempts' => 5, 'decay_seconds' => 3600],
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();
        }
    }

    public function test_requests_exceeding_limit_return_429(): void
    {
        $created = $this->service->create($this->user, [
            'name'       => 'Prod',
            'rate_limit' => ['max_attempts' => 2, 'decay_seconds' => 3600],
        ]);

        $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();
        $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();

        $this->withToken($created->plaintext)->getJson('/test/me')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_per_key_rate_limit_overrides_global_default(): void
    {
        // Generous global default, restrictive per-key limit.
        config()->set('api-keys.rate_limit.default', ['max_attempts' => 1000, 'decay_seconds' => 3600]);

        $created = $this->service->create($this->user, [
            'name'       => 'Prod',
            'rate_limit' => ['max_attempts' => 1, 'decay_seconds' => 3600],
        ]);

        $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();
        $this->withToken($created->plaintext)->getJson('/test/me')->assertStatus(429);
    }

    public function test_rate_limit_resets_after_decay_window(): void
    {
        $created = $this->service->create($this->user, [
            'name'       => 'Prod',
            'rate_limit' => ['max_attempts' => 1, 'decay_seconds' => 60],
        ]);

        $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();
        $this->withToken($created->plaintext)->getJson('/test/me')->assertStatus(429);

        $this->travel(61)->seconds();

        $this->withToken($created->plaintext)->getJson('/test/me')->assertOk();
    }
}
