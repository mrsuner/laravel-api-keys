<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\ValueObjects\CreatedKey;

/**
 * The single public entry point for API key lifecycle operations.
 *
 * Bound as a singleton by ApiKeyServiceProvider — resolve via
 * app(ApiKeyService::class) from the host application.
 */
class ApiKeyService
{
    /**
     * Base58 alphabet (Bitcoin) — omits 0, O, I and l to avoid visual ambiguity.
     */
    private const BASE58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Create and persist an API key, returning the one-time plaintext.
     *
     * @param  array<string, mixed>  $attributes  name, environment, abilities,
     *                                             rate_limit, expires_at
     *
     * @throws InvalidArgumentException When environment is not configured.
     */
    public function create(Model $user, array $attributes): CreatedKey
    {
        $environment = $attributes['environment'] ?? 'live';
        $environments = (array) config('api-keys.environments', ['live', 'test']);

        if (! in_array($environment, $environments, true)) {
            throw new InvalidArgumentException("Unsupported environment [{$environment}].");
        }

        $token      = $this->generateToken();
        $appPrefix  = (string) config('api-keys.key_prefix', 'sk');
        $plaintext  = $appPrefix.'_'.$environment.'_'.$token;
        $keyPrefix  = $appPrefix.'_'.$environment.'_'.substr($token, 0, 8);

        /** @var ApiKey $key */
        $key = ApiKey::query()->create([
            'user_id'     => $user->getKey(),
            'name'        => $attributes['name'],
            'key_prefix'  => $keyPrefix,
            'key_hash'    => $this->hash($token),
            'environment' => $environment,
            'abilities'   => $attributes['abilities'] ?? null,
            'rate_limit'  => $attributes['rate_limit'] ?? null,
            'expires_at'  => $attributes['expires_at'] ?? null,
            'is_active'   => true,
        ]);

        $this->audit('api_key.created', $user, [
            'metadata' => ['name' => $key->name, 'key_prefix' => $key->key_prefix],
        ]);

        return new CreatedKey($plaintext, $key);
    }

    /**
     * Resolve a raw bearer key to its model — no side effects, no usability
     * filtering (the caller decides via ApiKey::isUsable()). Designed to be a
     * single indexed read so it adds no measurable latency per request.
     */
    public function resolve(string $rawKey): ?ApiKey
    {
        $parsed = $this->parseToken($rawKey);

        if ($parsed === null) {
            return null;
        }

        /** @var ApiKey|null $key */
        $key = ApiKey::query()
            ->where('key_hash', $this->hash($parsed['token']))
            ->where('environment', $parsed['environment'])
            ->with('user')
            ->first();

        return $key;
    }

    /**
     * Immediately revoke a key.
     */
    public function revoke(ApiKey $key, ?string $reason = null): void
    {
        $key->forceFill([
            'is_active'  => false,
            'revoked_at' => now(),
        ])->save();

        $this->audit('api_key.revoked', $key->user, [
            'metadata' => ['key_prefix' => $key->key_prefix, 'reason' => $reason],
        ]);
    }

    /**
     * Rotate a key, optionally keeping the old one valid until $overlapUntil.
     *
     * The new key inherits name, environment, abilities, rate_limit and expiry
     * from the old one.
     */
    public function rotate(ApiKey $oldKey, ?Carbon $overlapUntil = null): CreatedKey
    {
        if (! $oldKey->isUsable()) {
            throw new InvalidArgumentException('Only active API keys can be rotated.');
        }

        $new = $this->create($oldKey->user, [
            'name'        => $oldKey->name,
            'environment' => $oldKey->environment,
            'abilities'   => $oldKey->abilities,
            'rate_limit'  => $oldKey->rate_limit,
            'expires_at'  => $oldKey->expires_at,
        ]);

        if ($overlapUntil !== null) {
            // Keep the old key alive until the overlap window closes.
            $oldKey->forceFill(['revoked_at' => $overlapUntil])->save();
        } else {
            $this->revoke($oldKey);
        }

        $this->audit('api_key.rotated', $oldKey->user, [
            'metadata' => [
                'old_prefix'    => $oldKey->key_prefix,
                'new_prefix'    => $new->model->key_prefix,
                'overlap_until' => $overlapUntil?->toIso8601String(),
            ],
        ]);

        return $new;
    }

    /**
     * Generate a base58 token of the configured length using a CSPRNG.
     */
    public function generateToken(): string
    {
        $length   = max(1, (int) config('api-keys.token_length', 24));
        $alphabet = self::BASE58;
        $max      = strlen($alphabet) - 1;
        $token    = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }

        return $token;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Extract the environment and token segment from a raw key, or null if the
     * format is wrong.
     *
     * Expected format: {prefix}_{environment}_{token}
     *
     * @return array{environment: string, token: string}|null
     */
    private function parseToken(string $rawKey): ?array
    {
        $appPrefix    = (string) config('api-keys.key_prefix', 'sk');
        $environments = (array) config('api-keys.environments', ['live', 'test']);

        $segments = explode('_', $rawKey);

        // prefix, environment, token (the base58 token contains no underscores).
        if (count($segments) !== 3) {
            return null;
        }

        [$prefix, $environment, $token] = $segments;

        if ($prefix !== $appPrefix || ! in_array($environment, $environments, true)) {
            return null;
        }

        if ($token === '') {
            return null;
        }

        return ['environment' => $environment, 'token' => $token];
    }

    /**
     * Emit an audit entry when the host application provides the helper.
     *
     * @param  array<string, mixed>  $payload
     */
    private function audit(string $event, ?Model $auditable, array $payload): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, $auditable, $payload);
        }
    }
}
