<?php

declare(strict_types=1);

use App\Routes\PlaceSearchPace;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Date::setTestNow('2026-10-14 12:00:00');
    Sleep::fake(syncWithCarbon: true);
});

afterEach(function (): void {
    Sleep::fake(value: false);
    Date::setTestNow();
});

it('gives the place search turns at least a full second apart, for all of Traceway', function (): void {
    $repository = new Repository(new ArrayStore);
    $placeSearchPace = new PlaceSearchPace($repository);

    $first = $placeSearchPace->take();
    $second = new PlaceSearchPace($repository)->take();
    Date::setTestNow('2026-10-14 12:00:01');
    $third = $placeSearchPace->take();
    Date::setTestNow('2026-10-14 12:00:02');

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($third)->toBeFalse()
        ->and($placeSearchPace->take())->toBeTrue();
    Sleep::assertNeverSlept();
});

it('waits for the next turn when asked to', function (): void {
    $placeSearchPace = new PlaceSearchPace(new Repository(new ArrayStore));
    $placeSearchPace->take();

    expect($placeSearchPace->take(waitMilliseconds: 2000))->toBeTrue()
        ->and(Date::now()->toTimeString())->toBe('12:00:02');
});

it('rests for a minute after the place search turned Traceway away, without waiting', function (): void {
    $placeSearchPace = new PlaceSearchPace(new Repository(new ArrayStore));

    $placeSearchPace->rest();
    Date::setTestNow('2026-10-14 12:00:59');
    $resting = $placeSearchPace->take(waitMilliseconds: 2000);
    Date::setTestNow('2026-10-14 12:01:00');

    expect($resting)->toBeFalse()
        ->and($placeSearchPace->take())->toBeTrue();
    Sleep::assertNeverSlept();
});
