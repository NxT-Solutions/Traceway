<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How often a finished UTC day that is not kept yet came back from Web Analytics with nothing at all. After a few
     * empty answers hours apart, the day is kept with no page loads and is not asked again. A row holds a count and a
     * time, never a person.
     */
    public function up(): void
    {
        Schema::create('traffic_empty_days', function (Blueprint $blueprint): void {
            $blueprint->date('day')->primary();
            $blueprint->unsignedSmallInteger('answers')->default(0);
            $blueprint->timestamp('last_counted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_empty_days');
    }
};
