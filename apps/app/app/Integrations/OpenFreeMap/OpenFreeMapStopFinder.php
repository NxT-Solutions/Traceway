<?php

declare(strict_types=1);

namespace App\Integrations\OpenFreeMap;

use App\Domain\Routing\Details\FoundStop;
use App\Domain\Routing\Details\RouteDetailer;
use App\Domain\Routing\Details\StopFinder;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Distance;
use App\Domain\Routing\Sport;
use App\Integrations\OpenFreeMap\Requests\TileIndexRequest;
use App\Integrations\OpenFreeMap\Requests\TileRequest;
use RuntimeException;
use Saloon\Http\Response;
use Throwable;

/**
 * Water, toilets, and cafés from the map tiles: the points of interest in
 * the most detailed tiles hold every one OpenStreetMap has, named or not.
 * It reads each tile the line passes within reach of.
 */
final readonly class OpenFreeMapStopFinder implements StopFinder
{
    /** The most detailed tiles, about 1.5 km across in Belgium. */
    private const int ZOOM = 14;

    /** A line is walked in steps this long, so no tile it passes is missed. */
    private const float STEP_METRES = 250.0;

    /**
     * More than the longest line the planner takes, 400 km, crosses up to
     * about 80° north: about one tile a kilometre in Belgium.
     */
    private const int MAX_TILES = 1_500;

    /** A real tile is well under a megabyte. */
    private const int MAX_TILE_BYTES = 4 << 20;

    private const float METRES_PER_DEGREE = 111_320.0;

    public function __construct(private OpenFreeMapConnector $openFreeMapConnector) {}

    public function along(Sport $sport, array $line): array
    {
        $tiles = $this->tiles($line, RouteDetailer::stopRadiusMetres($sport));

        throw_if(count($tiles) > self::MAX_TILES, RuntimeException::class, 'That line is too long to look for stops along.');

        $path = $this->tilePath();
        $requests = [];

        foreach ($tiles as $key => [$x, $y]) {
            $requests[$key] = new TileRequest($path, self::ZOOM, $x, $y);
        }

        /** @var array<string, array<string, FoundStop>> $found */
        $found = [];

        // Each tile is read as it comes, so only its stops are kept.
        $this->openFreeMapConnector->pool(
            requests: $requests,
            concurrency: max(1, min(count($requests), 8)),
            responseHandler: function (Response $response, int|string $key) use (&$found, $tiles): void {
                try {
                    $found[(string) $key] = $this->stopsIn($response, $tiles[(string) $key]);
                } catch (Throwable) {
                    // Tried again below.
                }
            },
            exceptionHandler: static function (): void {
                // Tried again below.
            },
        )->send()->wait();

        // A pool tries each tile once. One it missed gets another go on its
        // own, where the request's retries apply.
        foreach ($requests as $key => $tileRequest) {
            $found[$key] ??= $this->stopsIn($this->openFreeMapConnector->send($tileRequest), $tiles[$key]);
        }

        // A tile repeats the points just past its edge, so two tiles can hold
        // the same stop.
        return array_values(array_merge(...array_values($found)));
    }

    /**
     * @param  array{0: int, 1: int}  $tile
     * @return array<string, FoundStop>
     */
    private function stopsIn(Response $response, array $tile): array
    {
        throw_if($response->failed(), RuntimeException::class, 'A map tile did not load.');
        throw_if(($response->getPsrResponse()->getBody()->getSize() ?? 0) > self::MAX_TILE_BYTES, RuntimeException::class, 'A map tile was too big.');

        $body = $response->body();

        throw_if(strlen($body) > self::MAX_TILE_BYTES, RuntimeException::class, 'A map tile was too big.');

        $stops = [];

        foreach (VectorTile::points($body, 'poi') as $feature) {
            $kind = $this->kind($feature['tags']);

            if (! $kind instanceof StopKind) {
                continue;
            }

            $name = $feature['tags']['name'] ?? null;

            foreach ($feature['points'] as [$across, $down]) {
                [$longitude, $latitude] = $this->coordinate($tile[0] + $across, $tile[1] + $down);
                $stops[sprintf('%s:%.6f,%.6f', $kind->value, $longitude, $latitude)] = new FoundStop(
                    $kind,
                    $longitude,
                    $latitude,
                    is_string($name) && $name !== '' ? $name : null,
                );
            }
        }

        return $stops;
    }

    /**
     * The tile path of the current build, such as
     * /planet/20261011_080001_pt/{z}/{x}/{y}.pbf.
     */
    private function tilePath(): string
    {
        $response = $this->openFreeMapConnector->send(new TileIndexRequest);

        throw_if($response->failed(), RuntimeException::class, 'The map tiles did not say where they are.');

        $template = $response->json('tiles.0');
        $path = is_string($template) ? parse_url($template, PHP_URL_PATH) : null;

        throw_unless(
            is_string($path) && str_contains($path, '{z}') && str_contains($path, '{x}') && str_contains($path, '{y}'),
            RuntimeException::class,
            'The map tiles did not say where they are.',
        );

        return $path;
    }

    /**
     * @param  array<string, string|int|float|bool>  $tags
     */
    private function kind(array $tags): ?StopKind
    {
        $subclass = $tags['subclass'] ?? null;
        $class = $tags['class'] ?? null;

        return StopKind::fromAmenity(is_string($subclass) ? $subclass : null)
            ?? StopKind::fromAmenity(is_string($class) ? $class : null);
    }

    /**
     * Every tile within the search radius of the line, walked in short
     * steps, keyed by its position.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return array<string, array{0: int, 1: int}>
     */
    private function tiles(array $line, int $radiusMetres): array
    {
        $tiles = [];
        $previous = null;

        foreach ($line as $point) {
            $steps = $previous === null ? 1 : max(1, (int) ceil(Distance::metres($previous, $point) / self::STEP_METRES));

            for ($step = 1; $step <= $steps; $step++) {
                $at = $previous === null ? $point : [
                    $previous[0] + ($point[0] - $previous[0]) * $step / $steps,
                    $previous[1] + ($point[1] - $previous[1]) * $step / $steps,
                ];
                $latitudePad = $radiusMetres / self::METRES_PER_DEGREE;
                $longitudePad = $radiusMetres / (self::METRES_PER_DEGREE * max(0.01, cos(deg2rad($at[1]))));

                foreach ([[-1, -1], [-1, 1], [1, -1], [1, 1]] as [$east, $north]) {
                    [$x, $y] = $this->tile($at[0] + $east * $longitudePad, $at[1] + $north * $latitudePad);
                    $tiles[$x.'/'.$y] = [$x, $y];
                }
            }

            $previous = $point;
        }

        return $tiles;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function tile(float $longitude, float $latitude): array
    {
        $count = 2 ** self::ZOOM;
        $latitude = max(-85.0511, min(85.0511, $latitude));
        $radians = deg2rad($latitude);
        $x = (int) floor(($longitude + 180.0) / 360.0 * $count);
        $y = (int) floor((1.0 - log(tan($radians) + 1.0 / cos($radians)) / M_PI) / 2.0 * $count);

        return [max(0, min($count - 1, $x)), max(0, min($count - 1, $y))];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function coordinate(float $x, float $y): array
    {
        $count = 2 ** self::ZOOM;

        return [
            $x / $count * 360.0 - 180.0,
            rad2deg(atan(sinh(M_PI * (1.0 - 2.0 * $y / $count)))),
        ];
    }
}
