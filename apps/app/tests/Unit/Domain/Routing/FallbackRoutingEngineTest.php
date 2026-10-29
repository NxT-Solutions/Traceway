<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\FallbackRoutingEngine;
use App\Domain\Routing\FollowPoint;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LineFollower;
use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

function fallbackQuery(): RouteQuery
{
    return new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    );
}

/**
 * An engine that draws every route $metres long, or fails with $failure, and counts what it was asked.
 */
function countingEngine(int $metres, ?RuntimeException $failure = null): LineFollower&RoutingEngine
{
    return new class($metres, $failure) implements LineFollower, RoutingEngine
    {
        public int $asked = 0;

        public function __construct(private readonly int $metres, private readonly ?RuntimeException $failure = null) {}

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            return $this->answer($routeQuery);
        }

        /**
         * @return GeneratedRoute[]
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            return [$this->answer($routeQuery)];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            return $this->answer($routeQuery);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            return $this->answer($routeQuery);
        }

        public function match(RouteQuery $routeQuery, array $line): ?GeneratedRoute
        {
            $this->asked++;

            return null;
        }

        public function follow(RouteQuery $routeQuery, array $points): GeneratedRoute
        {
            return $this->answer($routeQuery);
        }

        private function answer(RouteQuery $routeQuery): GeneratedRoute
        {
            $this->asked++;

            if ($this->failure instanceof RuntimeException) {
                throw $this->failure;
            }

            return new GeneratedRoute($routeQuery, [[3.72, 51.05], [3.8, 51.1]], $this->metres, 0);
        }
    };
}

it('draws with the main engine while it answers, and never asks the backup', function (): void {
    $main = countingEngine(20_000);
    $backup = countingEngine(19_000);

    $routes = new FallbackRoutingEngine($main, $backup)->candidates(fallbackQuery());

    expect($routes[0]->distanceMetres)->toBe(20_000)
        ->and($backup->asked)->toBe(0);
});

it('draws with the backup while the main engine is busy', function (string $asked): void {
    $backup = countingEngine(19_000);
    $fallbackRoutingEngine = new FallbackRoutingEngine(countingEngine(20_000, new MapServerBusy('The map server did not answer.')), $backup);
    $line = [[3.72, 51.05], [3.8, 51.1]];

    $route = match ($asked) {
        'a loop' => $fallbackRoutingEngine->candidates(fallbackQuery())[0],
        'a route' => $fallbackRoutingEngine->route(fallbackQuery()),
        'a built line' => $fallbackRoutingEngine->trace(fallbackQuery(), $line),
        'a loop closed' => $fallbackRoutingEngine->closeLoop(fallbackQuery(), $line, $line[1], $line[0]),
        'a followed line' => $fallbackRoutingEngine->follow(fallbackQuery(), [new FollowPoint($line[0]), new FollowPoint($line[1])]),
    };

    expect($route->distanceMetres)->toBe(19_000)
        ->and($backup->asked)->toBe(1);
})->with(['a loop', 'a route', 'a built line', 'a loop closed', 'a followed line']);

it('believes the main engine when no road fits, and does not ask the backup', function (): void {
    $backup = countingEngine(19_000);
    $fallbackRoutingEngine = new FallbackRoutingEngine(countingEngine(20_000, new RuntimeException('No loop matched those filters.')), $backup);

    expect(fn (): array => $fallbackRoutingEngine->candidates(fallbackQuery()))->toThrow(RuntimeException::class, 'No loop matched those filters.')
        ->and($backup->asked)->toBe(0);
});

it('still says the map server is busy when the backup cannot draw either', function (): void {
    $fallbackRoutingEngine = new FallbackRoutingEngine(
        countingEngine(20_000, new MapServerBusy('The map server did not answer.')),
        countingEngine(19_000, new RuntimeException('The cycling router did not return a loop.')),
    );

    try {
        $fallbackRoutingEngine->candidates(fallbackQuery());
        $caught = null;
    } catch (MapServerBusy $mapServerBusy) {
        $caught = $mapServerBusy;
    }

    expect($caught?->getMessage())->toBe('The map server did not answer.')
        ->and($caught?->getPrevious()?->getMessage())->toBe('The cycling router did not return a loop.');
});

it('snaps a whole line with the main engine only', function (): void {
    $main = countingEngine(20_000);
    $backup = countingEngine(19_000);

    $matched = new FallbackRoutingEngine($main, $backup)->match(fallbackQuery(), [[3.72, 51.05], [3.8, 51.1]]);

    expect($matched)->toBeNull()
        ->and($main->asked)->toBe(1)
        ->and($backup->asked)->toBe(0);
});
