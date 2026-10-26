<?php

declare(strict_types=1);

namespace App\Integrations\GraphHopper;

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\LineDistance;

/**
 * The part of a GraphHopper request that keeps a route off the rider's
 * avoided stretches. GraphHopper skips an area, not a way, so each stretch
 * becomes a thin band around its line, priced at zero. A custom model needs
 * the flexible mode, so the request turns contraction hierarchies off.
 *
 * The engine plans nothing yet. It merges this into each request once a
 * graph is imported.
 */
final class GraphHopperAvoid
{
    /** Half the width of the band around a stretch. */
    public const float HALF_WIDTH_METRES = 6.0;

    /**
     * The band stops this far short of each junction, so the roads that meet
     * the stretch there stay open.
     */
    public const float JUNCTION_METRES = 15.0;

    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * @param  list<AvoidedStretch>  $avoid
     * @return array<string, mixed>
     */
    public static function customModel(array $avoid): array
    {
        if ($avoid === []) {
            return [];
        }

        $features = [];
        $priority = [];

        foreach ($avoid as $index => $avoidedStretch) {
            $area = 'avoid_'.$index;
            $features[] = [
                'type' => 'Feature',
                'id' => $area,
                'properties' => (object) [],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [self::band(self::shortOfJunctions($avoidedStretch->shape))]],
            ];
            $priority[] = ['if' => 'in_'.$area, 'multiply_by' => '0'];
        }

        return [
            'ch.disable' => true,
            'custom_model' => [
                'priority' => $priority,
                'areas' => ['type' => 'FeatureCollection', 'features' => $features],
            ],
        ];
    }

    /**
     * The middle of the line, short of the junction at each end. A stretch
     * too short to trim keeps its middle third.
     *
     * @param  list<array{0: float, 1: float}>  $shape
     * @return list<array{0: float, 1: float}>
     */
    public static function shortOfJunctions(array $shape): array
    {
        $lineDistance = LineDistance::of($shape);
        $total = $lineDistance->totalMetres();
        $trim = $total > self::JUNCTION_METRES * 3 ? self::JUNCTION_METRES : $total / 3;
        $middle = [$lineDistance->pointAt($trim)];

        foreach ($shape as $index => $point) {
            $along = $lineDistance->cumulative[$index] ?? 0.0;

            if ($along > $trim && $along < $total - $trim) {
                $middle[] = $point;
            }
        }

        $middle[] = $lineDistance->pointAt($total - $trim);

        return $middle;
    }

    /**
     * A closed ring around the line: out along one side, back along the other.
     *
     * @param  list<array{0: float, 1: float}>  $shape
     * @return list<array{0: float, 1: float}>
     */
    public static function band(array $shape): array
    {
        $count = count($shape);
        $left = [];
        $right = [];

        foreach ($shape as $index => $point) {
            $previous = $shape[max(0, $index - 1)];
            $next = $shape[min($count - 1, $index + 1)];
            $lonScale = self::METRES_PER_DEGREE * max(0.01, cos(deg2rad($point[1])));
            $dx = ($next[0] - $previous[0]) * $lonScale;
            $dy = ($next[1] - $previous[1]) * self::METRES_PER_DEGREE;
            $length = hypot($dx, $dy);

            if ($length < 0.001) {
                $dx = 1.0;
                $dy = 0.0;
                $length = 1.0;
            }

            $offsetLon = (-$dy / $length) * self::HALF_WIDTH_METRES / $lonScale;
            $offsetLat = ($dx / $length) * self::HALF_WIDTH_METRES / self::METRES_PER_DEGREE;
            $left[] = [$point[0] + $offsetLon, $point[1] + $offsetLat];
            $right[] = [$point[0] - $offsetLon, $point[1] - $offsetLat];
        }

        $ring = array_merge($left, array_reverse($right));
        $ring[] = $ring[0];

        return $ring;
    }
}
