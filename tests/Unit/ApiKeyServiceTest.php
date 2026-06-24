<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\AuditSpy;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        AuditSpy::reset();
        $this->service = app(ApiKeyService::class);
        $this->user = User::factory()->create();
    }

    public function test_generates_key_with_correct_prefix_format(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $this->assertMatchesRegularExpression('/^sk_live_[1-9A-HJ-NP-Za-km-z]{24}$/', $created->plaintext);
    }

    public function test_generated_token_uses_base58_charset(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $token = substr($created->plaintext, strlen('sk_live_'));
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

        $this->assertSame(24, strlen($token));
        $this->assertSame('', str_replace(str_split($alphabet), '', $token));
    }

    public function test_key_hash_is_not_the_plaintext_token(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $token = substr($created->plaintext, strlen('sk_live_'));

        $this->assertNotSame($token, $created->model->key_hash);
        $this->assertSame(hash('sha256', $token), $created->model->key_hash);
    }

    public function test_resolve_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->service->resolve('sk_live_'.str_repeat('A', 24)));
    }

    public function test_resolve_returns_null_for_wrong_format(): void
    {
        $this->assertNull($this->service->resolve('not-a-key'));
        $this->assertNull($this->service->resolve('sk_prod_ABC'));      // bad environment
        $this->assertNull($this->service->resolve('xx_live_ABCDEF'));   // bad prefix
        $this->assertNull($this->service->resolve('1|sanctumtoken'));   // sanctum-style
    }

    public function test_resolve_returns_model_for_valid_key(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $resolved = $this->service->resolve($created->plaintext);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($created->model));
        $this->assertTrue($resolved->relationLoaded('user'));
    }

    public function test_revoke_sets_is_active_false_and_revoked_at(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $this->service->revoke($created->model);

        $fresh = $created->model->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->revoked_at);
    }

    public function test_rotate_creates_new_key_with_same_abilities(): void
    {
        $created = $this->service->create($this->user, [
            'name'      => 'Prod',
            'abilities' => ['read:orders', 'write:orders'],
        ]);

        $rotated = $this->service->rotate($created->model);

        $this->assertNotSame($created->model->id, $rotated->model->id);
        $this->assertSame(['read:orders', 'write:orders'], $rotated->model->abilities);
        $this->assertSame('Prod', $rotated->model->name);
    }

    public function test_rotate_with_overlap_sets_future_revoked_at_on_old_key(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $overlapUntil = now()->addHour();
        $this->service->rotate($created->model, $overlapUntil);

        $old = $created->model->fresh();
        $this->assertNotNull($old->revoked_at);
        $this->assertTrue($old->revoked_at->isFuture());
        $this->assertTrue($old->isUsable());
    }

    public function test_rotate_without_overlap_immediately_revokes_old_key(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);

        $this->service->rotate($created->model);

        $old = $created->model->fresh();
        $this->assertFalse($old->is_active);
        $this->assertFalse($old->isUsable());
    }

    public function test_rotate_rejects_revoked_key(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $this->service->revoke($created->model);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active API keys can be rotated.');

        $this->service->rotate($created->model->fresh());
    }

    public function test_create_emits_audit_log(): void
    {
        $this->service->create($this->user, ['name' => 'Prod']);

        $this->assertTrue(AuditSpy::has('api_key.created'));
    }

    public function test_revoke_emits_audit_log(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        AuditSpy::reset();

        $this->service->revoke($created->model, 'manual');

        $events = AuditSpy::for('api_key.revoked');
        $this->assertCount(1, $events);
        $this->assertSame('manual', $events[0]['payload']['metadata']['reason']);
    }

    public function test_rotate_emits_audit_log(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        AuditSpy::reset();

        $this->service->rotate($created->model);

        $this->assertTrue(AuditSpy::has('api_key.rotated'));
    }

    public function test_audit_metadata_never_contains_key_hash(): void
    {
        $created = $this->service->create($this->user, ['name' => 'Prod']);
        $this->service->rotate($created->model);

        foreach (AuditSpy::$events as $event) {
            $this->assertStringNotContainsString(
                'key_hash',
                json_encode($event['payload'], JSON_THROW_ON_ERROR),
            );
        }
    }

    public function test_create_throws_for_unknown_environment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create($this->user, ['name' => 'Prod', 'environment' => 'staging']);
    }
}
