<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A rider's account at a service Traceway sends routes to, such as Wahoo. The tokens are encrypted by the model.
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

        // Which saved route, or leg, went to which service, so a second send updates it there.
        // Leg 0 is the whole route.
        Schema::create('sent_routes', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignUuid('saved_route_id')->constrained('saved_routes')->cascadeOnDelete();
            $blueprint->unsignedTinyInteger('leg')->default(0);
            $blueprint->string('service', 32);
            $blueprint->string('external_id')->nullable();
            $blueprint->string('external_url')->nullable();
            $blueprint->timestamp('sent_at');
            $blueprint->unique(['saved_route_id', 'leg', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_routes');
        Schema::dropIfExists('route_service_accounts');
    }
};
