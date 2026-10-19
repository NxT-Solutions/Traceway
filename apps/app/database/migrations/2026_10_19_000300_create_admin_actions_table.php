<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The activity log of the admin area: who did what to whose account, and when. When either account is deleted
     * its side becomes empty, so the log keeps no trace of who that was.
     */
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->string('action', 32);
            $blueprint->json('details')->nullable();
            $blueprint->timestamp('created_at')->index();
            $blueprint->index(['rider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
