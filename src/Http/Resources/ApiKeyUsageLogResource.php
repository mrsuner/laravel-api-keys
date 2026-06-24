<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mrsuner\ApiKeys\Models\ApiKeyUsageLog;

/**
 * @mixin ApiKeyUsageLog
 */
class ApiKeyUsageLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'method'           => $this->method,
            'endpoint'         => $this->endpoint,
            'ip'               => $this->ip,
            'status_code'      => $this->status_code,
            'response_time_ms' => $this->response_time_ms,
            'created_at'       => $this->created_at->toIso8601String(),
        ];
    }
}
