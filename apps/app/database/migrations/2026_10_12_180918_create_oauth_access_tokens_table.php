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
        Schema::create('oauth_access_tokens', function (Blueprint $blueprint): void {
            $blueprint->char('id', 80)->primary();
            $blueprint->foreignId('user_id')->nullable()->index();
            $blueprint->foreignUuid('client_id');
            $blueprint->string('name')->nullable();
            $blueprint->text('scopes')->nullable();
            $blueprint->boolean('revoked');
            $blueprint->timestamps();
            $blueprint->dateTime('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_access_tokens');
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
