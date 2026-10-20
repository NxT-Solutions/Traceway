<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rider's account at another service, such as Google or Wahoo. It logs the rider in, and some also send
     * routes. The Wahoo and Ride with GPS rows move over as they are: their keys are encrypted with the app key
     * alone, so the ciphertext copies unchanged. They have no number from the service yet, so they cannot log
     * anyone in until `php artisan connections:identify`, which the deploy runs, asks each service for it.
     */
    public function up(): void
    {
        Schema::create('connected_accounts', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->string('provider', 32);
            $blueprint->string('provider_user_id', 191)->nullable();
            $blueprint->string('account_label', 191)->nullable();
            $blueprint->text('access_token')->nullable();
            $blueprint->text('refresh_token')->nullable();
            $blueprint->timestamp('expires_at')->nullable();
            $blueprint->json('scopes')->nullable();
            $blueprint->boolean('needs_reconnect')->default(value: false);
            $blueprint->timestamp('connected_at');
            $blueprint->timestamp('last_signed_in_at')->nullable();
            $blueprint->timestamp('last_sent_at')->nullable();
            $blueprint->timestamps();

            $blueprint->unique(['user_id', 'provider']);
            // One account at a service belongs to one Traceway account. SQLite lets several old rows without a
            // number share the index.
            $blueprint->unique(['provider', 'provider_user_id']);
        });

        if (Schema::hasTable('route_service_accounts')) {
            DB::statement(
                'INSERT INTO connected_accounts (id, user_id, provider, access_token, refresh_token, expires_at, needs_reconnect, connected_at, last_sent_at, created_at, updated_at) '
                .'SELECT id, user_id, service, access_token, refresh_token, expires_at, needs_reconnect, connected_at, last_sent_at, created_at, updated_at FROM route_service_accounts',
            );

            Schema::drop('route_service_accounts');
        }
    }

    public function down(): void
    {
        Schema::create('route_service_accounts', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->string('service', 32);
            $blueprint->text('access_token');
            $blueprint->text('refresh_token')->nullable();
            $blueprint->timestamp('expires_at')->nullable();
            $blueprint->boolean('needs_reconnect')->default(value: false);
            $blueprint->timestamp('connected_at');
            $blueprint->timestamp('last_sent_at')->nullable();
            $blueprint->timestamps();

            $blueprint->unique(['user_id', 'service']);
        });

        DB::statement(
            'INSERT INTO route_service_accounts (id, user_id, service, access_token, refresh_token, expires_at, needs_reconnect, connected_at, last_sent_at, created_at, updated_at) '
            ."SELECT id, user_id, provider, access_token, refresh_token, expires_at, needs_reconnect, connected_at, last_sent_at, created_at, updated_at FROM connected_accounts WHERE provider IN ('wahoo', 'ride_with_gps') AND access_token IS NOT NULL",
        );

        Schema::drop('connected_accounts');
    }
};
