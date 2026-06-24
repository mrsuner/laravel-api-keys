<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $api_key_id
 * @property string $method
 * @property string $endpoint
 * @property string $ip
 * @property int $status_code
 * @property int|null $response_time_ms
 * @property \Illuminate\Support\Carbon $created_at
 */
class ApiKeyUsageLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'status_code'      => 'integer',
        'response_time_ms' => 'integer',
        'created_at'       => 'datetime',
    ];

    /**
     * @return BelongsTo<ApiKey, ApiKeyUsageLog>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
