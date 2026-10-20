<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function passwordOptionalMigration(): Migration
{
    $migration = require database_path('migrations/2026_10_20_000100_make_password_optional_on_users_table.php');

    throw_unless($migration instanceof Migration, RuntimeException::class, 'The migration is not a migration.');

    return $migration;
}

function passwordOptionalSequence(): mixed
{
    return DB::table('sqlite_sequence')->where('name', 'users')->value('seq');
}

it("keeps a deleted rider's id from going to a new rider when SQLite builds the users table again", function (string $direction): void {
    $users = User::factory()->count(3)->create();
    $deleted = $users->last()->id;
    $users->last()->delete();

    $migration = passwordOptionalMigration();
    $direction === 'up' ? $migration->up() : $migration->down();

    expect(passwordOptionalSequence())->toEqual($deleted)
        ->and(User::factory()->create()->id)->toBe($deleted + 1);
})->with(['up', 'down']);

it('makes the password optional, and required again on the way back with one nobody knows', function (): void {
    $user = User::factory()->create(['password' => null]);
    $migration = passwordOptionalMigration();

    $migration->down();

    expect(DB::table('users')->where('id', $user->id)->value('password'))->toBeString()->not->toBe('');

    $migration->up();

    expect(fn (): User => User::factory()->create(['password' => null]))->not->toThrow(Throwable::class);
});
