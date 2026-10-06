<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sport');
            $table->string('surface');
            $table->string('elevation');
            $table->string('roads');
            $table->unsignedInteger('distance_metres');
            $table->unsignedInteger('ascent_metres')->default(0);
            $table->json('coordinates');
            $table->json('user_coordinates')->nullable();
            $table->json('finish_coordinates')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_routes');
    }
};
