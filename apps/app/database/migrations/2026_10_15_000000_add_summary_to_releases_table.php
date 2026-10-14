<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $blueprint): void {
            $blueprint->text('summary')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('summary');
        });
    }
};
