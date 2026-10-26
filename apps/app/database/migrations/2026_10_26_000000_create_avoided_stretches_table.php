<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avoided_stretches', function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->double('latitude');
            $blueprint->double('longitude');
            $blueprint->unsignedBigInteger('way_id')->nullable();
            $blueprint->string('name', 120)->nullable();
            $blueprint->json('shape');
            $blueprint->timestamps();

            $blueprint->index(['user_id', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avoided_stretches');
    }
};
