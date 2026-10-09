<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_routes', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->string('name');
            $blueprint->string('sport');
            $blueprint->string('surface');
            $blueprint->string('elevation');
            $blueprint->string('roads');
            $blueprint->unsignedInteger('distance_metres');
            $blueprint->unsignedInteger('ascent_metres')->default(0);
            $blueprint->json('coordinates');
            $blueprint->json('user_coordinates')->nullable();
            $blueprint->json('finish_coordinates')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_routes');
    }
};
