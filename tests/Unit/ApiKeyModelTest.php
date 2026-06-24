<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class ApiKeyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_usable_returns_false_when_is_active_false(): void
    {
        $key = new ApiKey(['is_active' => false]);

        $this->assertFalse($key->isUsable());
    }

    public function test_is_usable_returns_false_when_revoked_at_in_past(): void
    {
        $key = new ApiKey(['is_active' => true, 'revoked_at' => now()->subMinute()]);

        $this->assertFalse($key->isUsable());
    }

    public function test_is_usable_returns_true_when_revoked_at_in_future(): void
    {
        $key = new ApiKey(['is_active' => true, 'revoked_at' => now()->addHour()]);

        $this->assertTrue($key->isUsable());
    }

    public function test_is_usable_returns_false_when_expires_at_in_past(): void
    {
        $key = new ApiKey(['is_active' => true, 'expires_at' => now()->subMinute()]);

        $this->assertFalse($key->isUsable());
    }

    public function test_can_returns_true_for_null_abilities(): void
    {
        $key = new ApiKey(['abilities' => null]);

        $this->assertTrue($key->can('read:anything'));
    }

    public function test_can_returns_true_for_wildcard_ability(): void
    {
        $key = new ApiKey(['abilities' => ['*']]);

        $this->assertTrue($key->can('write:orders'));
    }

    public function test_can_returns_true_for_matching_ability(): void
    {
        $key = new ApiKey(['abilities' => ['read:orders']]);

        $this->assertTrue($key->can('read:orders'));
    }

    public function test_can_returns_false_for_missing_ability(): void
    {
        $key = new ApiKey(['abilities' => ['read:orders']]);

        $this->assertFalse($key->can('write:orders'));
    }

    public function test_effective_rate_limit_falls_back_to_config_default(): void
    {
        config()->set('api-keys.rate_limit.default', ['max_attempts' => 1000, 'decay_seconds' => 3600]);

        $key = new ApiKey(['rate_limit' => null]);

        $this->assertSame(['max_attempts' => 1000, 'decay_seconds' => 3600], $key->effectiveRateLimit());
    }

    public function test_effective_rate_limit_uses_per_key_override(): void
    {
        $key = new ApiKey(['rate_limit' => ['max_attempts' => 5, 'decay_seconds' => 60]]);

        $this->assertSame(['max_attempts' => 5, 'decay_seconds' => 60], $key->effectiveRateLimit());
    }

    public function test_key_hash_is_hidden_from_serialisation(): void
    {
        $user = User::factory()->create();
        $created = app(ApiKeyService::class)->create($user, ['name' => 'Prod']);

        $array = $created->model->toArray();

        $this->assertArrayNotHasKey('key_hash', $array);
        $this->assertStringNotContainsString('key_hash', $created->model->toJson());
    }

    public function test_masked_prefix_appends_bullets(): void
    {
        $key = new ApiKey(['key_prefix' => 'sk_live_A3F9K2MN']);

        $this->assertSame('sk_live_A3F9K2MN'.str_repeat('•', 8), $key->maskedPrefix());
    }
}
