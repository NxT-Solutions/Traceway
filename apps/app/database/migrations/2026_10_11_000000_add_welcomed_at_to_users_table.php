<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The welcome mail now waits for a confirmed email. Every account opened
     * before this change got its welcome at registration, so it is marked
     * as welcomed and does not get a second one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->timestamp('welcomed_at')->nullable()->after('email_verified_at');
        });

        DB::table('users')->update(['welcomed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('welcomed_at');
        });
    }
};
