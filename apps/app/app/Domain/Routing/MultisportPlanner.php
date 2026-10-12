<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use RuntimeException;

/**
 * Draws each land leg with the routing engine and keeps the swim as drawn.
 * The geometry always comes from the engine. This only decides where each
 * leg starts and ends.
 */
final readonly class MultisportPlanner
{
    public function __construct(private RoutingEngine $routingEngine) {}

    public function plan(MultisportQuery $multisportQuery): MultisportRoute
    {
        $legs = $multisportQuery->swim instanceof SwimLeg ? [PlannedLeg::swim($multisportQuery->swim)] : [];
        $from = $multisportQuery->transition();

        foreach ($multisportQuery->legs as $position => $landLeg) {
            if ($multisportQuery->layout === Layout::Loops) {
                $legs[] = $this->loop($landLeg, $from, $position);

                continue;
            }

            /** @var array{0: float, 1: float} $finish */
            $finish = $landLeg->finish;
            $legs[] = $this->handover($landLeg, $from, $finish);
            $from = $finish;
        }

        return new MultisportRoute($multisportQuery->layout, $legs);
    }

    /**
     * @param  array{0: float, 1: float}  $transition
     */
    private function loop(LandLeg $landLeg, array $transition, int $position): PlannedLeg
    {
        $best = $this->routingEngine->candidates($this->query($landLeg, Shape::Loop, $transition))[0]
            ?? throw new RuntimeException(sprintf('No loop came back for leg %d.', $position + 1));

        return new PlannedLeg($landLeg->sport, $best->coordinates, $best->distanceMetres, $best->ascentMetres);
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private function handover(LandLeg $landLeg, array $from, array $to): PlannedLeg
    {
        $generatedRoute = $this->routingEngine->trace($this->query($landLeg, Shape::PointToPoint, $from), [$from, $to]);

        return new PlannedLeg($landLeg->sport, $generatedRoute->coordinates, $generatedRoute->distanceMetres, $generatedRoute->ascentMetres);
    }

    /**
     * @param  array{0: float, 1: float}  $start
     */
    private function query(LandLeg $landLeg, Shape $shape, array $start): RouteQuery
    {
        return new RouteQuery(
            sport: $landLeg->sport,
            shape: $shape,
            distanceMetres: $landLeg->distanceMetres,
            surface: $landLeg->surface,
            elevation: $landLeg->elevation,
            startLatitude: $start[1],
            startLongitude: $start[0],
            preferQuiet: $landLeg->preferQuiet,
        );
    }
}
