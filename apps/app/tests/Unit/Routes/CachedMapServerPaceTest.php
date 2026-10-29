<?php

declare(strict_types=1);

use App\Domain\Routing\MapServerBusy;
use App\Routes\CachedMapServerPace;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow('2026-10-29 10:20:00');
});

afterEach(function (): void {
    Date::setTestNow();
});

it('shares one minute of requests across all of Traceway, and starts again the next minute', function (): void {
    $repository = new Repository(new ArrayStore);

    new CachedMapServerPace($repository, perMinute: 10)->admit(6);
    new CachedMapServerPace($repository, perMinute: 10)->admit(4);

    expect(fn () => new CachedMapServerPace($repository, perMinute: 10)->admit(1))
        ->toThrow(MapServerBusy::class, 'Traceway asked the map server for enough this minute.');

    Date::setTestNow('2026-10-29 10:21:00');
    new CachedMapServerPace($repository, perMinute: 10)->admit(10);
});

it('gives back the share of a batch that did not fit, so a smaller one still does', function (): void {
    $cachedMapServerPace = new CachedMapServerPace(new Repository(new ArrayStore), perMinute: 10);
    $cachedMapServerPace->admit(6);

    expect(fn () => $cachedMapServerPace->admit(6))->toThrow(MapServerBusy::class);

    $cachedMapServerPace->admit(4);

    expect(fn () => $cachedMapServerPace->admit(1))->toThrow(MapServerBusy::class);
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
