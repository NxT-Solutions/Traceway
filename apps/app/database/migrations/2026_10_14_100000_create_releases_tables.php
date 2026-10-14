<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('tag', 64)->unique();
            $blueprint->string('name');
            $blueprint->text('notes');
            $blueprint->timestamp('published_at')->index();
            $blueprint->timestamps();
        });

        Schema::create('release_reads', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('release_id')->constrained()->cascadeOnDelete();
            $blueprint->timestamp('read_at');
            $blueprint->unique(['user_id', 'release_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_reads');
        Schema::dropIfExists('releases');
    }
};
