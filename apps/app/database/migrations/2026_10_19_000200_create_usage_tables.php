<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily totals of how often each part of Traceway is used. A row names a day and a metric, never a person.
     * The last time a rider was signed in sits on their own account, for the active riders count.
     */
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $blueprint): void {
            $blueprint->date('day');
            $blueprint->string('metric', 48);
            $blueprint->unsignedBigInteger('count')->default(0);
            $blueprint->primary(['day', 'metric']);
            $blueprint->index(['metric', 'day']);
        });

        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->timestamp('last_seen_at')->nullable()->index()->after('welcomed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['last_seen_at']);
            $blueprint->dropColumn('last_seen_at');
        });

        Schema::dropIfExists('usage_counters');
    }
};
