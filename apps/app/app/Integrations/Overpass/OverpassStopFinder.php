<?php

declare(strict_types=1);

namespace App\Integrations\Overpass;

use App\Domain\Routing\Details\FoundStop;
use App\Domain\Routing\Details\RouteDetailer;
use App\Domain\Routing\Details\StopFinder;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Distance;
use App\Domain\Routing\Sport;
use App\Integrations\Overpass\Requests\StopsAlongRequest;
use RuntimeException;

final readonly class OverpassStopFinder implements StopFinder
{
    /** Each box covers about this much of the line. */
    private const float BOX_METRES = 3_000.0;

    private const float METRES_PER_DEGREE = 111_320.0;

    public function __construct(private OverpassConnector $overpassConnector) {}

    public function along(Sport $sport, array $line): array
    {
        $response = $this->overpassConnector->send(new StopsAlongRequest(
            $this->boxes($line, RouteDetailer::stopRadiusMetres($sport)),
        ));

        throw_if($response->failed(), RuntimeException::class, 'The map did not answer for stops.');

        // A query that ran out of time still answers 200, with a remark and
        // what it found so far. That is not "no stops", so it is not kept.
        $remark = $response->json('remark');
        throw_if(is_string($remark) && str_contains($remark, 'runtime error'), RuntimeException::class, 'The map ran out of time looking for stops.');

        $elements = $response->json('elements');

        if (! is_array($elements)) {
            return [];
        }

        $stops = [];

        foreach ($elements as $element) {
            if (! is_array($element) || ! is_numeric($element['lat'] ?? null) || ! is_numeric($element['lon'] ?? null)) {
                continue;
            }

            $tags = $element['tags'] ?? [];
            $tags = is_array($tags) ? $tags : [];
            $amenity = $tags['amenity'] ?? null;
            $kind = StopKind::fromAmenity(is_string($amenity) ? $amenity : null);
            $name = $tags['name'] ?? null;

            if ($kind instanceof StopKind) {
                $stops[] = new FoundStop(
                    $kind,
                    (float) $element['lon'],
                    (float) $element['lat'],
                    is_string($name) && $name !== '' ? $name : null,
                );
            }
        }

        return $stops;
    }

    /**
     * Cuts the line into stretches of a few kilometres, and draws a box
     * around each one, as wide again as the search radius on every side.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{south: float, west: float, north: float, east: float}>
     */
    private function boxes(array $line, int $radiusMetres): array
    {
        $boxes = [];
        $stretch = [];
        $metres = 0.0;
        $previous = null;

        foreach ($line as $point) {
            if ($previous !== null) {
                $metres += Distance::metres($previous, $point);
            }

            $stretch[] = $point;
            $previous = $point;

            if ($metres >= self::BOX_METRES) {
                $boxes[] = $this->box($stretch, $radiusMetres);
                $stretch = [$point];
                $metres = 0.0;
            }
        }

        if (count($stretch) > 1 || $boxes === []) {
            $boxes[] = $this->box($stretch, $radiusMetres);
        }

        return $boxes;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return array{south: float, west: float, north: float, east: float}
     */
    private function box(array $points, int $radiusMetres): array
    {
        $first = $points[0] ?? [0.0, 0.0];
        $south = $first[1];
        $north = $first[1];
        $west = $first[0];
        $east = $first[0];

        foreach ($points as [$longitude, $latitude]) {
            $south = min($south, $latitude);
            $north = max($north, $latitude);
            $west = min($west, $longitude);
            $east = max($east, $longitude);
        }

        $latitudePad = $radiusMetres / self::METRES_PER_DEGREE;
        $longitudePad = $radiusMetres / (self::METRES_PER_DEGREE * max(0.01, cos(deg2rad($first[1]))));

        return [
            'south' => max(-90.0, $south - $latitudePad),
            'west' => max(-180.0, $west - $longitudePad),
            'north' => min(90.0, $north + $latitudePad),
            'east' => min(180.0, $east + $longitudePad),
        ];
    }
}
