<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Web Analytics totals of each finished UTC day, kept because Cloudflare answers only the last week in full.
     * A row holds counts, page paths, referrer hosts, and country names, never a person.
     */
    public function up(): void
    {
        Schema::create('traffic_days', function (Blueprint $blueprint): void {
            $blueprint->date('day')->primary();
            $blueprint->unsignedBigInteger('page_loads')->default(0);
            $blueprint->unsignedBigInteger('visits')->default(0);
            $blueprint->json('top_pages');
            $blueprint->json('top_referrers');
            $blueprint->json('countries');
            $blueprint->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_days');
    }
};
