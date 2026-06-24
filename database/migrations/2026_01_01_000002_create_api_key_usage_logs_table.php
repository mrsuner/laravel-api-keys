<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_key_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained('api_keys')->cascadeOnDelete();

            $table->string('method', 8);     // GET, POST, etc.
            $table->string('endpoint', 255); // Normalized path (no IDs: /users/{id} not /users/42)
            $table->string('ip', 45);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['api_key_id', 'created_at']);
            $table->index('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_usage_logs');
    }
};
