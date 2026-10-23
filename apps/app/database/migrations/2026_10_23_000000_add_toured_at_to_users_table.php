<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The planner shows a new rider around once, on their first visit. Riders who used the planner before the
     * tour existed already found their way, so they count as shown around; they can still open it themselves.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->timestamp('toured_at')->nullable()->after('welcomed_at');
        });

        DB::table('users')->update(['toured_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('toured_at');
        });
    }
};
