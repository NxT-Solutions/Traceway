<?php

declare(strict_types=1);

use App\Domain\Routing\Details\Cue;
use App\Domain\Routing\Details\FoundStop;
use App\Domain\Routing\Details\ProfilePoint;
use App\Domain\Routing\Details\RoadDescriber;
use App\Domain\Routing\Details\RoadDescription;
use App\Domain\Routing\Details\RoadSurface;
use App\Domain\Routing\Details\RouteDetailer;
use App\Domain\Routing\Details\Stop;
use App\Domain\Routing\Details\StopFinder;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Details\SurfaceShare;
use App\Domain\Routing\Details\Turn;
use App\Domain\Routing\Sport;

/**
 * About 2.8 km due east.
 *
 * @return list<array{0: float, 1: float}>
 */
function eastLine(): array
{
    return [[4.0, 51.0], [4.02, 51.0], [4.04, 51.0]];
}

/**
 * @param  list<ProfilePoint>  $profile
 * @param  list<Cue>  $cues
 */
function roadDescriber(array $profile = [], array $cues = []): RoadDescriber
{
    return new readonly class($profile, $cues) implements RoadDescriber
    {
        /**
         * @param  list<ProfilePoint>  $profile
         * @param  list<Cue>  $cues
         */
        public function __construct(private array $profile, private array $cues) {}

        public function describe(Sport $sport, array $line): RoadDescription
        {
            return new RoadDescription($this->profile, [new SurfaceShare(RoadSurface::Paved, 2800)], $this->cues);
        }
    };
}

/**
 * @param  list<FoundStop>|null  $stops  Null for a server that does not answer.
 */
function stopFinder(?array $stops): StopFinder
{
    return new readonly class($stops) implements StopFinder
    {
        /**
         * @param  list<FoundStop>|null  $stops
         */
        public function __construct(private ?array $stops) {}

        public function along(Sport $sport, array $line): array
        {
            return $this->stops ?? throw new RuntimeException('Busy.');
        }
    };
}

it('totals the climb from the profile', function (): void {
    $routeDetails = new RouteDetailer(
        roadDescriber([new ProfilePoint(0, 10.0), new ProfilePoint(1400, 60.0), new ProfilePoint(2800, 20.0)]),
        stopFinder([]),
    )->for(Sport::Road, eastLine());

    expect($routeDetails->ascentMetres)->toBe(50)
        ->and($routeDetails->descentMetres)->toBe(40)
        ->and($routeDetails->surfaces)->toHaveCount(1);
});

it('keeps a long profile to a size a chart and a watch can read', function (): void {
    $profile = [];

    for ($index = 0; $index < 5000; $index++) {
        $profile[] = new ProfilePoint($index, 10.0);
    }

    $routeDetails = new RouteDetailer(roadDescriber($profile), stopFinder([]))->for(Sport::Road, eastLine());

    expect($routeDetails->profile)->toHaveCount(RouteDetailer::MAX_PROFILE_POINTS)
        ->and($routeDetails->profile[0]->distanceMetres)->toBe(0)
        ->and(array_last($routeDetails->profile)?->distanceMetres)->toBe(4999);
});

it('places stops on the line and drops one too far off it', function (): void {
    $routeDetails = new RouteDetailer(roadDescriber(), stopFinder([
        new FoundStop(StopKind::Water, 4.02, 51.0005, 'Tap'),
        new FoundStop(StopKind::Cafe, 4.03, 51.01),
    ]))->for(Sport::Road, eastLine());

    expect($routeDetails->stops)->toHaveCount(1)
        ->and($routeDetails->stops[0]->kind)->toBe(StopKind::Water)
        ->and($routeDetails->stops[0]->name)->toBe('Tap')
        ->and($routeDetails->stops[0]->latitude)->toEqualWithDelta(51.0, 0.00001)
        ->and($routeDetails->stops[0]->distanceMetres)->toBeGreaterThan(1300)->toBeLessThan(1500);
});

it('keeps one stop of a kind per stretch', function (): void {
    $routeDetails = new RouteDetailer(roadDescriber(), stopFinder([
        new FoundStop(StopKind::Cafe, 4.010, 51.0),
        new FoundStop(StopKind::Cafe, 4.011, 51.0),
        new FoundStop(StopKind::Toilet, 4.011, 51.0),
        new FoundStop(StopKind::Cafe, 4.030, 51.0),
    ]))->for(Sport::Walk, eastLine());

    expect(array_map(fn (Stop $stop): string => $stop->kind->value, $routeDetails->stops))->toBe(['cafe', 'toilet', 'cafe']);
});

it('still describes the route when the stop finder does not answer', function (): void {
    $routeDetails = new RouteDetailer(roadDescriber([new ProfilePoint(0, 5.0)]), stopFinder(stops: null))->for(Sport::Road, eastLine());

    expect($routeDetails->stops)->toBe([])
        ->and($routeDetails->profile)->toHaveCount(1);
});

it('drops a turn at the start and one that repeats the turn before it', function (): void {
    $routeDetails = new RouteDetailer(roadDescriber(cues: [
        new Cue(5, 4.0001, 51.0, Turn::Right),
        new Cue(1400, 4.02, 51.0, Turn::Left, 'Mill Road'),
        new Cue(1410, 4.0201, 51.0, Turn::Right),
        new Cue(2100, 4.03, 51.0, Turn::SlightLeft),
    ]), stopFinder([]))->for(Sport::Road, eastLine());

    expect(array_map(fn (Cue $cue): string => $cue->turn->value, $routeDetails->cues))->toBe(['left', 'slight_left'])
        ->and($routeDetails->cues[0]->street)->toBe('Mill Road');
});

it('asks bikes to look further off the line than feet', function (): void {
    expect(RouteDetailer::stopRadiusMetres(Sport::Gravel))->toBeGreaterThan(RouteDetailer::stopRadiusMetres(Sport::Run));
});
