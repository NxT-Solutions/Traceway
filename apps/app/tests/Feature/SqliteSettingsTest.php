<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The web, queue, and scheduler containers share one SQLite file, so the
 * settings it opens with decide whether two requests at once both land.
 */
function sqliteFile(): Connection
{
    $path = tempnam(sys_get_temp_dir(), 'traceway-sqlite-');
    $config = config('database.connections.sqlite');
    Config::set('database.connections.sqlite-file', [...(is_array($config) ? $config : []), 'database' => $path]);
    DB::purge('sqlite-file');

    return DB::connection('sqlite-file');
}

afterEach(function (): void {
    $path = config('database.connections.sqlite-file.database');
    DB::purge('sqlite-file');

    foreach (is_string($path) ? [$path, $path.'-wal', $path.'-shm'] : [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
});

it('opens the database so writers wait their turn and readers never wait', function (): void {
    $connection = sqliteFile();

    expect($connection->selectOne('pragma journal_mode')->journal_mode)->toBe('wal')
        ->and($connection->selectOne('pragma busy_timeout')->timeout)->toBe(5000)
        ->and($connection->selectOne('pragma synchronous')->synchronous)->toBe(1);
});

it('takes the write lock when a transaction begins, so reading then writing never fails halfway', function (): void {
    $connection = sqliteFile();
    $connection->statement('create table counters (name text primary key, hits integer)');

    $other = new PDO('sqlite:'.$connection->getDatabaseName(), options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $other->exec('pragma busy_timeout = 0');

    $connection->transaction(function () use ($other): void {
        expect(fn (): int|false => $other->exec('begin immediate'))->toThrow(PDOException::class, 'database is locked');
    });

    expect($other->exec('begin immediate'))->toBe(0);
    $other->exec('rollback');
});
