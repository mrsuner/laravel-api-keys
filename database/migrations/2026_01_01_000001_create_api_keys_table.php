<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();

            // Owner — a User from the host application. The boilerplate keys
            // users by ULID, so the foreign key is a ULID column.
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();

            // Human-readable label set by the user ("Production server").
            $table->string('name');

            /*
             * Stored prefix for display (e.g. "sk_live_A3F9K2MN"). Never the
             * full key. Format: {prefix}_{environment}_{first_8_chars_of_token}.
             * Lets admin UIs identify a key without ever holding the secret.
             */
            $table->string('key_prefix', 32)->unique();

            /*
             * SHA-256 hash of the token segment only (no prefix/environment).
             * Compared on every authenticated request.
             */
            $table->string('key_hash', 64)->unique();

            // live | test (extensible by host app via config).
            $table->string('environment', 16)->default('live');

            /*
             * Sanctum-style abilities array.
             * Null = all abilities (superkey). Empty array = no abilities.
             */
            $table->json('abilities')->nullable();

            /*
             * Optional per-key rate limit override.
             * Null = use the global default from config.
             * Format: { "max_attempts": 1000, "decay_seconds": 3600 }
             */
            $table->json('rate_limit')->nullable();

            // Hard expiry. Null = never expires.
            $table->timestamp('expires_at')->nullable();

            // Soft revocation. Does not delete usage history.
            $table->boolean('is_active')->default(true);

            // Set during rotation to allow an overlap window before the old key
            // dies. A future revoked_at means the key is still usable until then.
            $table->timestamp('revoked_at')->nullable();

            // Tracking (updated asynchronously, see TrackApiKeyUsage).
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->unsignedBigInteger('request_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index('environment');
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
