<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\ValueObjects;

use Mrsuner\ApiKeys\Models\ApiKey;

/**
 * Carries the one-time plaintext key alongside the persisted model.
 *
 * Returned by ApiKeyService::create() and ApiKeyService::rotate(). The
 * plaintext is never stored and must be surfaced to the caller exactly once.
 */
final class CreatedKey
{
    public function __construct(
        public readonly string $plaintext,
        public readonly ApiKey $model,
    ) {}
}
