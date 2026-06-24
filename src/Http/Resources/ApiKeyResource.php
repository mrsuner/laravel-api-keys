<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mrsuner\ApiKeys\Models\ApiKey;

/**
 * @mixin ApiKey
 */
class ApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // key_hash never appears here — it is in the model's $hidden array and
        // is not referenced below. The plaintext key is never on the model.
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'key_prefix'    => $this->maskedPrefix(),
            'environment'   => $this->environment,
            'abilities'     => $this->abilities,
            'rate_limit'    => $this->effectiveRateLimit(),
            'is_active'     => $this->is_active,
            'is_usable'     => $this->isUsable(),
            'expires_at'    => $this->expires_at?->toIso8601String(),
            'revoked_at'    => $this->revoked_at?->toIso8601String(),
            'last_used_at'  => $this->last_used_at?->toIso8601String(),
            'last_used_ip'  => $this->last_used_ip,
            'request_count' => $this->request_count,
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
