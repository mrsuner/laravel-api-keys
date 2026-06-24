<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Key prefix
    |--------------------------------------------------------------------------
    |
    | The product prefix used for generated keys. With "sk", keys look like
    | "sk_live_XXXX". The middleware only intercepts bearer tokens that begin
    | with "{key_prefix}_" so Sanctum's own (prefix-less) tokens fall through
    | untouched.
    |
    */
    'key_prefix' => env('API_KEY_PREFIX', 'sk'),

    /*
    |--------------------------------------------------------------------------
    | Allowed environment tags
    |--------------------------------------------------------------------------
    |
    | The middle segment of a key ("sk_live_...", "sk_test_..."). Extend this
    | list to add custom environment tags for your host application.
    |
    */
    'environments' => ['live', 'test'],

    /*
    |--------------------------------------------------------------------------
    | Token length
    |--------------------------------------------------------------------------
    |
    | Number of characters in the random token segment (base58 alphabet).
    |
    */
    'token_length' => (int) env('API_KEY_TOKEN_LENGTH', 24),

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | The owner model for an API key. On the boilerplate this is the ULID-keyed
    | App\Models\User with Sanctum's HasApiTokens.
    |
    */
    'user_model' => env('API_KEY_USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Default rate limit
    |--------------------------------------------------------------------------
    |
    | Applied to every key unless overridden per-key via the rate_limit column.
    |
    */
    'rate_limit' => [
        'default' => [
            'max_attempts'  => (int) env('API_KEY_RATE_LIMIT', 1000),
            'decay_seconds' => (int) env('API_KEY_RATE_DECAY', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage logging
    |--------------------------------------------------------------------------
    |
    | enabled:        Write a per-request row to api_key_usage_logs. When false,
    |                 only the aggregate counters on the key row are updated.
    | retention_days: Rows older than this are deleted by api-keys:prune.
    |
    */
    'usage_log' => [
        'enabled'        => (bool) env('API_KEY_USAGE_LOG', true),
        'retention_days' => (int) env('API_KEY_LOG_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Connection/queue name used for the async TrackApiKeyUsage job.
    |
    */
    'queue' => env('API_KEY_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Maximum active keys per user
    |--------------------------------------------------------------------------
    |
    | Enforced when a user creates a key through the user-facing API.
    | null = unlimited.
    |
    */
    'max_keys_per_user' => env('API_KEY_MAX_PER_USER', 10) === null
        ? null
        : (int) env('API_KEY_MAX_PER_USER', 10),

    /*
    |--------------------------------------------------------------------------
    | User-facing route wiring
    |--------------------------------------------------------------------------
    |
    | Endpoints for users managing their own keys. Mounted under the prefix and
    | the auth:sanctum guard.
    |
    */
    'user_route' => [
        'enabled'    => true,
        'prefix'     => 'v1/api-keys',
        'name'       => 'api-keys.',
        'middleware' => ['auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin route wiring
    |--------------------------------------------------------------------------
    |
    | enabled:    Master switch for the admin API. Also gated by the host's
    |             `boilerplate.admin.enabled` flag when present.
    | middleware: null = auto-detect. When the boilerplate's
    |             App\Http\Middleware\InternalIpWhitelist class is present, the
    |             full admin stack is applied; otherwise the package falls back
    |             to ['auth:sanctum'] so it works in any Laravel app.
    |
    */
    'admin_route' => [
        'enabled'    => true,
        'prefix'     => 'internal/admin/v1',
        'name'       => 'admin.api-keys.',
        'middleware' => null,
    ],
];
