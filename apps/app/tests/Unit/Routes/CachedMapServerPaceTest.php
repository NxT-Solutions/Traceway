<?php

declare(strict_types=1);

use App\Domain\Routing\MapServerBusy;
use App\Routes\CachedMapServerPace;
use Carbon\CarbonInterval;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Date::setTestNow('2026-10-29 10:20:00');
});

afterEach(function (): void {
    Date::setTestNow();
});

it('lets a whole share go at once across Traceway, then makes the next request wait its turn', function (): void {
    Sleep::fake(syncWithCarbon: true);
    $repository = new Repository(new ArrayStore);

    new CachedMapServerPace($repository, perMinute: 10)->admit(6);
    new CachedMapServerPace($repository, perMinute: 10)->admit(4);

    Sleep::assertNeverSlept();

    // Ten a minute is one every six seconds: the eleventh waits six.
    new CachedMapServerPace($repository, perMinute: 10)->admit(1);

    Sleep::assertSlept(fn (CarbonInterval $carbonInterval): bool => (int) $carbonInterval->totalMilliseconds === 6_000);
    Sleep::fake(value: false);
});

it('counts the server as busy rather than wait long, and leaves the share as it was', function (): void {
    Sleep::fake(syncWithCarbon: true);
    $cachedMapServerPace = new CachedMapServerPace(new Repository(new ArrayStore), perMinute: 10);
    $cachedMapServerPace->admit(10);

    expect(fn () => $cachedMapServerPace->admit(2))
        ->toThrow(MapServerBusy::class, 'Traceway asked the map server for enough this minute.');

    $cachedMapServerPace->admit(1);

    Sleep::assertSlept(fn (CarbonInterval $carbonInterval): bool => (int) $carbonInterval->totalMilliseconds === 6_000);
    Sleep::fake(value: false);
});

it('frees the share as time passes, and not all at once on the minute', function (): void {
    Sleep::fake(syncWithCarbon: true);
    Date::setTestNow('2026-10-29 10:20:59');
    $cachedMapServerPace = new CachedMapServerPace(new Repository(new ArrayStore), perMinute: 10);
    $cachedMapServerPace->admit(10);

    // One second later, on the next minute, the share is still nearly full.
    Date::setTestNow('2026-10-29 10:21:00');
    $cachedMapServerPace->admit(1);

    Sleep::assertSlept(fn (CarbonInterval $carbonInterval): bool => (int) $carbonInterval->totalMilliseconds === 5_000);

    // Two quiet minutes later, a whole share goes at once again.
    Date::setTestNow('2026-10-29 10:23:10');
    $cachedMapServerPace->admit(10);

    Sleep::assertSleptTimes(1);
    Sleep::fake(value: false);
});

it('stops every request while it rests, for two minutes', function (): void {
    $repository = new Repository(new ArrayStore);
    new CachedMapServerPace($repository)->rest();

    expect(fn () => new CachedMapServerPace($repository, perMinute: 0)->admit(1))
        ->toThrow(MapServerBusy::class, 'Traceway is giving the map server a rest.');

    Date::setTestNow('2026-10-29 10:22:01');
    new CachedMapServerPace($repository, perMinute: 0)->admit(1);
});

it('sets no limit, and takes no rest, for a map server Traceway runs itself', function (): void {
    $cachedMapServerPace = new CachedMapServerPace(new Repository(new ArrayStore), perMinute: 0);

    $cachedMapServerPace->admit(500);
    $cachedMapServerPace->rest();
    $cachedMapServerPace->admit(500);
})->throwsNoExceptions();

it('keeps a share and a rest for each server of its own', function (): void {
    $repository = new Repository(new ArrayStore);
    $valhalla = new CachedMapServerPace($repository, perMinute: 2);
    $backup = new CachedMapServerPace($repository, perMinute: 2, server: 'routing-backup');

    $valhalla->admit(2);
    $valhalla->rest();

    $backup->admit(2);

    expect(fn () => $valhalla->admit(1))->toThrow(MapServerBusy::class, 'Traceway is giving the map server a rest.')
        ->and(fn () => $backup->admit(1))->toThrow(MapServerBusy::class, 'Traceway asked the map server for enough this minute.');
});

it('gives turns about a second apart when asked to, for all of Traceway', function (): void {
    Sleep::fake(syncWithCarbon: true);
    $repository = new Repository(new ArrayStore);

    new CachedMapServerPace($repository, server: 'routing-backup', oneASecond: true)->admit(1);
    new CachedMapServerPace($repository, server: 'routing-backup', oneASecond: true)->admit(1);

    Sleep::assertSleptTimes(4);
    Sleep::fake(value: false);
});

it('counts the server as busy when no turn comes in time', function (): void {
    Sleep::fake();
    $repository = new Repository(new ArrayStore);
    $cachedMapServerPace = new CachedMapServerPace($repository, server: 'routing-backup', oneASecond: true);
    $cachedMapServerPace->admit(1);

    expect(fn () => $cachedMapServerPace->admit(1))->toThrow(MapServerBusy::class, 'Traceway waited too long for its turn at the map server.');

    Sleep::fake(value: false);
});
