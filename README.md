# Laravel API Keys

Developer API key management for Laravel — prefix-routed keys (`sk_live_…`), rotation
with an overlap window, per-key rate limits, abilities, and async usage tracking.

[![Tests](https://github.com/mrsuner/laravel-api-keys/actions/workflows/tests.yml/badge.svg)](https://github.com/mrsuner/laravel-api-keys/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mrsuner/laravel-api-keys.svg)](https://packagist.org/packages/mrsuner/laravel-api-keys)
[![License](https://img.shields.io/github/license/mrsuner/laravel-api-keys)](LICENSE)

It **layers over** Laravel Sanctum rather than replacing it: a resolved key authenticates
the request through the existing `auth:sanctum` stack, so every route, policy, and response
envelope keeps working unchanged. The package runs standalone in any Laravel 12/13 app and
auto-wires into [`mrsuner/laravel-api-boilerplate`](https://github.com/mrsuner/laravel-api-boilerplate)
when present (its `audit_log()` helper, `InternalIpWhitelist`, and `EnsureAdminAccess`
middleware are detected at runtime; none are required).

## Why not just Sanctum tokens?

| Concern          | Sanctum token          | Developer API key             |
| ---------------- | ---------------------- | ----------------------------- |
| Format           | Opaque hash, no prefix | `sk_live_XXXX` prefix-routed  |
| Rate limit       | Global throttle only   | Configurable per key          |
| Rotation         | Delete + recreate      | Rotate with overlap window    |
| Usage tracking   | `last_used_at` only    | IP + endpoint + request count |
| Admin visibility | Token list by user     | Revoke + per-key usage stats  |

## Installation

```bash
composer require mrsuner/laravel-api-keys "^1.0"
php artisan vendor:publish --tag=api-keys-config   # optional
php artisan migrate
```

The service provider is auto-discovered. It also registers the `api.key` and `api.ability`
middleware aliases, so you do **not** need to edit `bootstrap/app.php`.

## Key format

```
sk_live_A3F9K2MNPQRSTUVWXYZ23
└┬┘ └┬─┘ └─────────┬─────────┘
 │    │            └── random token (base58, configurable length)
 │    └─────────────── environment tag (live | test)
 └──────────────────── product prefix (config: api-keys.key_prefix)
```

Only a SHA-256 hash of the **token** segment is stored (`key_hash`), and authentication
also requires the raw key's environment segment to match the stored `environment`. The
plaintext key is returned exactly once, at creation and rotation. A plaintext `key_prefix`
(`sk_live_A3F9K2MN`) is kept for display so admin UIs can identify a key without the secret.

## Authenticating requests

Add `api.key` ahead of `auth:sanctum`. A bearer token that does not start with your configured
prefix falls straight through to Sanctum, so the same route accepts both kinds of credential.

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['api.key', 'auth:sanctum'])->group(function () {
    Route::get('/orders', OrderController::class)
        ->middleware('api.ability:read:orders');
});
```

Inside a controller the resolved key is available for inspection:

```php
$key = $request->attributes->get('api_key');   // Mrsuner\ApiKeys\Models\ApiKey
$key->can('write:orders');
```

## Managing keys programmatically

```php
use Mrsuner\ApiKeys\Services\ApiKeyService;

$service = app(ApiKeyService::class);

$created = $service->create($user, [
    'name'       => 'Production server',
    'environment'=> 'live',
    'abilities'  => ['read:orders', 'write:orders'], // null = superkey
    'rate_limit' => ['max_attempts' => 1000, 'decay_seconds' => 3600],
    'expires_at' => now()->addYear(),
]);

$created->plaintext;  // sk_live_… — show ONCE, never stored
$created->model;      // persisted ApiKey

$service->rotate($created->model, now()->addHour()); // overlap window
$service->revoke($created->model, reason: 'leaked');
$service->resolve($bearer);                           // ?ApiKey (single indexed read)
```

## HTTP API

### User endpoints (`auth:sanctum`, default prefix `v1/api-keys`)

| Method & path                       | Action                                  |
| ----------------------------------- | --------------------------------------- |
| `GET    /v1/api-keys`               | List own keys (masked)                  |
| `POST   /v1/api-keys`               | Create key — returns plaintext **once** |
| `GET    /v1/api-keys/{key}`         | Show key detail (never the secret)      |
| `PATCH  /v1/api-keys/{key}`         | Update name / abilities / rate_limit    |
| `POST   /v1/api-keys/{key}/rotate`  | Rotate (`overlap_minutes`, default 0)   |
| `DELETE /v1/api-keys/{key}`         | Revoke                                  |
| `GET    /v1/api-keys/{key}/usage`   | Usage stats + recent log entries        |

### Admin endpoints (default prefix `internal/admin/v1`)

Mounted under the boilerplate admin stack (`InternalIpWhitelist` + `auth:sanctum` +
`ability:admin` + `EnsureAdminAccess` when available) when detected, otherwise a minimal
`auth:sanctum` + `ability:admin` stack. Override with `api-keys.admin_route.middleware`.

| Method & path                                | Action                          |
| -------------------------------------------- | ------------------------------- |
| `GET    /internal/admin/v1/api-keys`         | List all keys                   |
| `GET    /internal/admin/v1/api-keys/{key}`   | Key detail + usage stats        |
| `DELETE /internal/admin/v1/api-keys/{key}`   | Revoke any key                  |
| `GET    /internal/admin/v1/users/{user}/api-keys` | All keys for a user        |

## Async usage tracking

Usage is recorded in the middleware's `terminate()` hook via the queued `TrackApiKeyUsage`
job, so it never adds latency to the response. Each request updates the key's aggregate
counters (`last_used_at`, `last_used_ip`, `request_count`) and — when
`api-keys.usage_log.enabled` is true — writes a normalized row to `api_key_usage_logs`
(`/v1/orders/42` is stored as `/v1/orders/{id}` to bound cardinality).

## Scheduled pruning

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('api-keys:prune')->daily();
```

Soft-deletes expired keys and deletes usage logs older than
`api-keys.usage_log.retention_days`.

## Configuration

See [`config/api-keys.php`](config/api-keys.php) — key prefix, allowed environments, token
length, user model, default/per-key rate limits, usage-log toggle and retention, queue,
`max_keys_per_user`, and the user/admin route wiring. Every value has an `.env` override:

```dotenv
API_KEY_PREFIX=sk
API_KEY_RATE_LIMIT=1000
API_KEY_USAGE_LOG=true
API_KEY_LOG_RETENTION_DAYS=90
API_KEY_QUEUE=default
API_KEY_MAX_PER_USER=10
```

## Audit events

`api_key.created`, `api_key.revoked`, `api_key.rotated`, `api_key.updated`,
`api_key.admin_revoked`, `api_key.expired`. Every event includes `key_prefix` in its
metadata; `key_hash` and the plaintext key are never logged. Emitted through the host's
`audit_log()` helper when present (no-op otherwise).

## Testing

```bash
composer install
composer test
```

The suite runs standalone via Orchestra Testbench against an in-memory SQLite database with a
ULID-keyed test user that mirrors the boilerplate.

## License

[MIT](LICENSE).
