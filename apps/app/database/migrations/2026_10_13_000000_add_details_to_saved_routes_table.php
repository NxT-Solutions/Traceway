<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_routes', function (Blueprint $blueprint): void {
            $blueprint->json('details')->nullable()->after('legs');
        });
    }

    public function down(): void
    {
        Schema::table('saved_routes', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('details');
        });
    }
};
