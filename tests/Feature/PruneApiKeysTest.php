<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Models\ApiKeyUsageLog;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\Tests\Fixtures\AuditSpy;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Mrsuner\ApiKeys\Tests\TestCase;

class PruneApiKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_soft_deletes_expired_keys_and_old_logs(): void
    {
        AuditSpy::reset();
        config()->set('api-keys.usage_log.retention_days', 90);

        $user = User::factory()->create();
        $service = app(ApiKeyService::class);

        $expired = $service->create($user, ['name' => 'Expired', 'expires_at' => now()->addDay()]);
        $expired->model->forceFill(['expires_at' => now()->subDay()])->save();

        $active = $service->create($user, ['name' => 'Active', 'expires_at' => now()->addYear()]);

        // One old usage log (pruned) and one recent (kept).
        ApiKeyUsageLog::query()->create([
            'api_key_id' => $active->model->id, 'method' => 'GET', 'endpoint' => '/old',
            'ip' => '127.0.0.1', 'status_code' => 200, 'created_at' => now()->subDays(120),
        ]);
        ApiKeyUsageLog::query()->create([
            'api_key_id' => $active->model->id, 'method' => 'GET', 'endpoint' => '/new',
            'ip' => '127.0.0.1', 'status_code' => 200, 'created_at' => now()->subDays(1),
        ]);

        $this->artisan('api-keys:prune')
            ->expectsOutputToContain('Pruned 1 expired keys, 1 usage log rows.')
            ->assertSuccessful();

        $this->assertSoftDeleted('api_keys', ['id' => $expired->model->id]);
        $this->assertNotSoftDeleted('api_keys', ['id' => $active->model->id]);
        $this->assertSame(1, ApiKeyUsageLog::query()->count());
        $this->assertTrue(AuditSpy::has('api_key.expired'));
    }
}
