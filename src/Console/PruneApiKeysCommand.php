<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Console;

use Illuminate\Console\Command;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Models\ApiKeyUsageLog;

class PruneApiKeysCommand extends Command
{
    protected $signature = 'api-keys:prune';

    protected $description = 'Soft-delete expired API keys and prune old usage logs.';

    public function handle(): int
    {
        $expiredKeys = ApiKey::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete(); // soft delete

        $retentionDays = (int) config('api-keys.usage_log.retention_days', 90);

        $prunedLogs = ApiKeyUsageLog::query()
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Pruned {$expiredKeys} expired keys, {$prunedLogs} usage log rows.");

        if (function_exists('audit_log')) {
            audit_log('api_key.expired', null, [
                'metadata' => ['count' => $expiredKeys, 'usage_logs_pruned' => $prunedLogs],
            ]);
        }

        return self::SUCCESS;
    }
}
