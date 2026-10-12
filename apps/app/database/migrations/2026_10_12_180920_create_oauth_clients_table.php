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
        Schema::create('oauth_clients', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->nullableMorphs('owner');
            $blueprint->string('name');
            $blueprint->string('secret')->nullable();
            $blueprint->string('provider')->nullable();
            $blueprint->text('redirect_uris');
            $blueprint->text('grant_types');
            $blueprint->boolean('revoked');
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
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
