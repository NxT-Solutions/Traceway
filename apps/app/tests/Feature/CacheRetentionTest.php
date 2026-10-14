<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('deletes expired cache entries and keeps the rest', function (): void {
    DB::table('cache')->insert([
        ['key' => 'details:drawn-yesterday', 'value' => 's:4:"line";', 'expiration' => now()->subDay()->getTimestamp()],
        ['key' => 'place-name:51.123,4.988', 'value' => 's:5:"Oevel";', 'expiration' => now()->addDays(29)->getTimestamp()],
    ]);

    $this->artisan('cache:prune-expired')
        ->expectsOutput('Deleted 1 expired cache entries.')
        ->assertSuccessful();

    expect(DB::table('cache')->pluck('key')->all())->toBe(['place-name:51.123,4.988']);
});

it('clears expired cache entries and failed messages every hour on the production scheduler', function (string $command): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, $command));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('0 * * * *');
})->with(['cache:prune-expired', 'queue:prune-failed --hours=24']);
