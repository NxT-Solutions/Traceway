<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rider who signs up with a connected account has no password until they set one. Laravel refuses a
     * password sign-in for a row without one.
     */
    public function up(): void
    {
        $this->keepingIds(function (): void {
            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->string('password')->nullable()->change();
            });
        });
    }

    /**
     * Gives every rider without a password one nobody knows, so the column can be required again. They set
     * their own from the reset link.
     */
    public function down(): void
    {
        foreach (DB::table('users')->whereNull('password')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['password' => Hash::make(bin2hex(random_bytes(32)))]);
        }

        $this->keepingIds(function (): void {
            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->string('password')->nullable(value: false)->change();
            });
        });
    }

    /**
     * SQLite changes a column by building the table again, which starts its id counter from the highest id left.
     * The ids of riders who deleted their account would then go to new riders, so the counter is put back.
     */
    private function keepingIds(Closure $change): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $change();

            return;
        }

        $last = DB::table('sqlite_sequence')->where('name', 'users')->value('seq');

        $change();

        if (! is_numeric($last)) {
            return;
        }

        $now = DB::table('sqlite_sequence')->where('name', 'users')->value('seq');

        if ($now === null) {
            DB::table('sqlite_sequence')->insert(['name' => 'users', 'seq' => (int) $last]);
        } elseif (is_numeric($now) && (int) $now < (int) $last) {
            DB::table('sqlite_sequence')->where('name', 'users')->update(['seq' => (int) $last]);
        }
    }
};
