<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Models\ApiKeyUsageLog;

/**
 * Writes per-request usage data off the request path so tracking never adds
 * latency to the API response. Dispatched from AuthenticateWithApiKey::terminate().
 */
final class TrackApiKeyUsage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly int $apiKeyId,
        public readonly string $method,
        public readonly string $endpoint,
        public readonly string $ip,
        public readonly int $statusCode,
        public readonly ?int $responseTimeMs = null,
    ) {
        $this->onQueue(config('api-keys.queue', 'default'));
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            if (config('api-keys.usage_log.enabled', true)) {
                ApiKeyUsageLog::query()->create([
                    'api_key_id'       => $this->apiKeyId,
                    'method'           => $this->method,
                    'endpoint'         => $this->endpoint,
                    'ip'               => $this->ip,
                    'status_code'      => $this->statusCode,
                    'response_time_ms' => $this->responseTimeMs,
                    'created_at'       => now(),
                ]);
            }

            ApiKey::query()->where('id', $this->apiKeyId)->update([
                'last_used_at'  => now(),
                'last_used_ip'  => $this->ip,
                'request_count' => DB::raw('request_count + 1'),
            ]);
        });
    }
}
