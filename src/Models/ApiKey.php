<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $user_id
 * @property string $name
 * @property string $key_prefix
 * @property string $key_hash
 * @property string $environment
 * @property array<int, string>|null $abilities
 * @property array<string, int>|null $rate_limit
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property int $request_count
 */
class ApiKey extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * The hash is a secret-equivalent and must never be serialised.
     *
     * @var array<int, string>
     */
    protected $hidden = ['key_hash'];

    protected $casts = [
        'abilities'     => 'array',
        'rate_limit'    => 'array',
        'expires_at'    => 'datetime',
        'revoked_at'    => 'datetime',
        'last_used_at'  => 'datetime',
        'is_active'     => 'boolean',
        'request_count' => 'integer',
    ];

    /**
     * @return BelongsTo<Model, ApiKey>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('api-keys.user_model', \App\Models\User::class));
    }

    /**
     * @return HasMany<ApiKeyUsageLog>
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiKeyUsageLog::class);
    }

    /**
     * Usable right now: active, not past its revocation, not past its expiry.
     *
     * A future revoked_at (the rotation overlap window) is still usable — this
     * is why the check is `isFuture()` rather than `is_null()`.
     */
    public function isUsable(): bool
    {
        return $this->is_active
            && ($this->revoked_at === null || $this->revoked_at->isFuture())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Ability check — mirrors Sanctum's tokenCan() semantics.
     * Null abilities = superkey (everything). "*" also grants everything.
     */
    public function can(string $ability): bool
    {
        if ($this->abilities === null) {
            return true;
        }

        return in_array($ability, $this->abilities, true)
            || in_array('*', $this->abilities, true);
    }

    /**
     * The per-key rate limit, or the global default from config.
     *
     * @return array{max_attempts: int, decay_seconds: int}
     */
    public function effectiveRateLimit(): array
    {
        return $this->rate_limit ?? config('api-keys.rate_limit.default');
    }

    /**
     * Masked display string for UIs ("sk_live_A3F9K2MN••••••••").
     */
    public function maskedPrefix(): string
    {
        return $this->key_prefix.str_repeat('•', 8);
    }

    /**
     * Active and currently usable.
     *
     * @param  Builder<ApiKey>  $query
     * @return Builder<ApiKey>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('revoked_at')->orWhere('revoked_at', '>', now());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
