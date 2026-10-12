<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_links', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $blueprint->string('provider', 32)->default('garmin');
            $blueprint->string('label')->nullable();
            $blueprint->string('pairing_code', 6)->nullable()->unique();
            $blueprint->string('pairing_secret_hash', 64)->nullable()->unique();
            $blueprint->timestamp('pairing_expires_at')->nullable();
            $blueprint->string('token_hash', 64)->nullable()->unique();
            $blueprint->timestamp('linked_at')->nullable();
            $blueprint->timestamp('last_used_at')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_links');
    }
};
