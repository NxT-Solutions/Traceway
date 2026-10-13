<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\Sport;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use Throwable;
use ValueError;

/**
 * Remembers the details of a line for an hour. The planner reads them when
 * the line is drawn, and saving the same line straight after reads them
 * again. Stops are kept only when the stop finder answered.
 */
final readonly class CachedRouteDetails implements RouteDetailsSource
{
    private const int SECONDS = 3600;

    public function __construct(
        private RouteDetailsSource $routeDetailsSource,
        private Repository $repository,
    ) {}

    public function for(Sport $sport, array $line): RouteDetails
    {
        $routeDetails = $this->road($sport, $line);

        try {
            return $routeDetails->withStops($this->stops($sport, $line));
        } catch (Throwable) {
            return $routeDetails;
        }
    }

    public function road(Sport $sport, array $line): RouteDetails
    {
        $key = $this->key('road', $sport, $line);
        $cached = $this->repository->get($key);

        if (is_array($cached)) {
            try {
                return RouteDetails::fromArray($cached);
            } catch (InvalidArgumentException|ValueError) {
                // An entry from an older shape is read again.
            }
        }

        $routeDetails = $this->routeDetailsSource->road($sport, $line);
        $this->repository->put($key, $routeDetails->toArray(), self::SECONDS);

        return $routeDetails;
    }

    public function stops(Sport $sport, array $line): array
    {
        $key = $this->key('stops', $sport, $line);
        $cached = $this->repository->get($key);

        if (is_array($cached)) {
            try {
                return RouteDetails::fromArray(['stops' => $cached, 'ascent_metres' => 0, 'descent_metres' => 0])->stops;
            } catch (InvalidArgumentException|ValueError) {
                // An entry from an older shape is read again.
            }
        }

        $stops = $this->routeDetailsSource->stops($sport, $line);
        $this->repository->put(
            $key,
            new RouteDetails([], 0, 0, [], [], $stops)->toArray()['stops'],
            self::SECONDS,
        );

        return $stops;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     */
    private function key(string $part, Sport $sport, array $line): string
    {
        return 'route-details:'.$part.':'.sha1($sport->value.'|'.json_encode($line, JSON_THROW_ON_ERROR));
    }
}
