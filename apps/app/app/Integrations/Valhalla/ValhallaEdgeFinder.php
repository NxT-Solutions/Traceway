<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla;

use App\Domain\Routing\AvoidedStretches;
use App\Domain\Routing\EdgeFinder;
use App\Domain\Routing\RoadEdge;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Domain\Routing\ValhallaCosting;
use App\Integrations\Valhalla\Requests\LocateRequest;
use RuntimeException;

/**
 * Valhalla's verbose locate answers with the way under a point: its OSM way,
 * its name, and its shape between the two junctions at either end. The point
 * kept is the middle of that shape, not where the tap landed: a tap next to a
 * junction lands on it, and an excluded junction closes every way through it.
 */
final readonly class ValhallaEdgeFinder implements EdgeFinder
{
    public function __construct(private ValhallaConnector $valhallaConnector) {}

    public function near(float $latitude, float $longitude, Sport $sport, Surface $surface): ?RoadEdge
    {
        $costing = ValhallaCosting::locate($sport, $surface);
        $response = $this->valhallaConnector->send(new LocateRequest(
            latitude: $latitude,
            longitude: $longitude,
            costing: $costing['costing'],
            costingOptions: $costing['costing_options'],
        ));

        throw_if($response->failed(), RuntimeException::class, 'The router did not find the way under the point.');

        $edge = $response->json('0.edges.0');

        if (! is_array($edge)) {
            return null;
        }

        $onLatitude = $edge['correlated_lat'] ?? null;
        $onLongitude = $edge['correlated_lon'] ?? null;
        $info = is_array($edge['edge_info'] ?? null) ? $edge['edge_info'] : [];
        $encoded = $info['shape'] ?? null;

        if (! is_numeric($onLatitude) || ! is_numeric($onLongitude) || ! is_string($encoded) || $encoded === '') {
            return null;
        }

        $point = [(float) $onLongitude, (float) $onLatitude];
        $shape = Polyline::decode($encoded);

        if (count($shape) < 2) {
            return null;
        }

        $wayId = $info['way_id'] ?? null;
        $names = is_array($info['names'] ?? null) ? $info['names'] : [];
        $name = $names[0] ?? null;

        $clipped = AvoidedStretches::clipped($shape, $point);
        $middle = AvoidedStretches::middle($clipped);

        return new RoadEdge(
            latitude: $middle[1],
            longitude: $middle[0],
            shape: $clipped,
            wayId: is_int($wayId) ? $wayId : null,
            name: is_string($name) && trim($name) !== '' ? mb_substr(trim($name), 0, 120) : null,
        );
    }
}
