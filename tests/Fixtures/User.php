<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;

/**
 * Minimal ULID-keyed user, mirroring the boilerplate's App\Models\User so the
 * api_keys.user_id foreign key (a ULID) behaves identically under test.
 */
class User extends Authenticatable implements HasApiTokensContract
{
    use HasApiTokens;
    use HasFactory;
    use HasUlids;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
