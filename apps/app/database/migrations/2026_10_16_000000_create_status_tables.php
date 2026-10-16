<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_checks', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('component', 32);
            $blueprint->string('state', 16);
            $blueprint->unsignedInteger('latency_ms');
            $blueprint->timestamp('checked_at')->index();
            // Covers the day counts, so the page reads no rows to count them.
            $blueprint->index(['component', 'checked_at', 'state']);
        });

        Schema::create('status_notices', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('title');
            $blueprint->text('body')->nullable();
            $blueprint->json('components');
            $blueprint->timestamp('started_at');
            $blueprint->timestamp('resolved_at')->nullable()->index();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_notices');
        Schema::dropIfExists('status_checks');
    }
};
