<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oauth_device_codes', function (Blueprint $blueprint): void {
            $blueprint->char('id', 80)->primary();
            $blueprint->foreignId('user_id')->nullable()->index();
            $blueprint->foreignUuid('client_id')->index();
            $blueprint->char('user_code', 8)->unique();
            $blueprint->text('scopes');
            $blueprint->boolean('revoked');
            $blueprint->dateTime('user_approved_at')->nullable();
            $blueprint->dateTime('last_polled_at')->nullable();
            $blueprint->dateTime('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_device_codes');
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        $connection = $this->connection ?? config('passport.connection');

        return is_string($connection) ? $connection : null;
    }
};
