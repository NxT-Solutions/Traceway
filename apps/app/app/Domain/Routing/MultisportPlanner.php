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
        $ridden = [];

        foreach ($multisportQuery->legs as $position => $landLeg) {
            if ($multisportQuery->layout === Layout::Loops) {
                $leg = $this->loop($landLeg, $from, $position, $ridden);
            } else {
                /** @var array{0: float, 1: float} $finish */
                $finish = $landLeg->finish;
                $leg = $this->handover($landLeg, $from, $finish, $ridden);
                $from = $finish;
            }

            $legs[] = $leg;
            $ridden = $ridden === [] ? $leg->coordinates : array_merge($ridden, array_slice($leg->coordinates, 1));
        }

        return new MultisportRoute($multisportQuery->layout, $legs);
    }

    /**
     * Of the loops the engine offers, the one that keeps off the roads the
     * earlier legs came back on.
     *
     * @param  array{0: float, 1: float}  $transition
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    private function loop(LandLeg $landLeg, array $transition, int $position, array $ridden): PlannedLeg
    {
        $best = CleanLeg::best($ridden, $this->routingEngine->candidates($this->query($landLeg, Shape::Loop, $transition)), $landLeg->distanceMetres)
            ?? throw new RuntimeException(sprintf('No loop came back for leg %d.', $position + 1));

        return new PlannedLeg($landLeg->sport, $best->coordinates, $best->distanceMetres, $best->ascentMetres);
    }

    /**
     * The way from one handover to the next. A stretched leg winds out to
     * its length. Any leg that would turn around on a road, its own or one
     * an earlier leg used, tries a detour to each side instead.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    private function handover(LandLeg $landLeg, array $from, array $to, array $ridden): PlannedLeg
    {
        $routeQuery = $this->query($landLeg, Shape::PointToPoint, $from);
        $generatedRoute = $this->routingEngine->trace($routeQuery, [$from, $to]);
        $stretch = $landLeg->stretch && $generatedRoute->distanceMetres < $landLeg->distanceMetres * 0.9;
        $target = $stretch ? $landLeg->distanceMetres : $generatedRoute->distanceMetres;
        $doubles = CleanLeg::doubledShare($ridden, $generatedRoute) > CleanLeg::DOUBLED;

        if (! $stretch && ! $doubles) {
            return $this->planned($landLeg, $generatedRoute);
        }

        // A detour to dodge a turnaround may run a little longer than the direct way.
        $detourLength = $stretch ? $target : (int) round($generatedRoute->distanceMetres * 1.3);
        $best = $this->detoured($routeQuery, $from, $to, $detourLength, $target, $generatedRoute, $ridden);

        return $this->planned($landLeg, $best);
    }

    /**
     * Traces a detour to each side, keeps the cleanest, moves its point once
     * to land nearer the length, and goes wider when every detour still
     * turns around.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    private function detoured(RouteQuery $routeQuery, array $from, array $to, int $detourLength, int $target, GeneratedRoute $generatedRoute, array $ridden): GeneratedRoute
    {
        $straight = Distance::metres($from, $to);
        $roadFactor = $straight > 50.0 ? max(1.0, $generatedRoute->distanceMetres / $straight) : 1.3;
        $tried = [$generatedRoute];
        $sides = [];

        foreach (Detour::vias($from, $to, $detourLength, $roadFactor) as $side => $via) {
            $line = $this->through($routeQuery, $from, $via, $to);
            $tried[] = $line;
            $sides[] = ['side' => $side, 'route' => $line];
        }

        $best = CleanLeg::best($ridden, $tried, $target) ?? $generatedRoute;
        $bestSide = null;

        foreach ($sides as $side) {
            if ($side['route'] === $best) {
                $bestSide = $side['side'];
            }
        }

        $gained = $best->distanceMetres - $generatedRoute->distanceMetres;

        if ($bestSide !== null && $gained > 0 && abs($best->distanceMetres - $target) > $target * 0.05) {
            $scale = max(0.3, min(3.0, ($target - $generatedRoute->distanceMetres) / $gained));
            $via = Detour::vias($from, $to, $detourLength, $roadFactor, $scale)[$bestSide] ?? null;

            if ($via !== null) {
                $tried[] = $this->through($routeQuery, $from, $via, $to);
            }
        }

        $best = CleanLeg::best($ridden, $tried, $target) ?? $generatedRoute;

        if (CleanLeg::doubledShare($ridden, $best) > CleanLeg::DOUBLED) {
            foreach (Detour::vias($from, $to, $detourLength, $roadFactor, 1.4) as $via) {
                $tried[] = $this->through($routeQuery, $from, $via, $to);
            }

            $best = CleanLeg::best($ridden, $tried, $target) ?? $generatedRoute;
        }

        return $best;
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $via
     * @param  array{0: float, 1: float}  $to
     */
    private function through(RouteQuery $routeQuery, array $from, array $via, array $to): GeneratedRoute
    {
        return CleanLeg::withoutSpurs($this->routingEngine->trace($routeQuery, [$from, $via, $to]), $from, $to);
    }

    private function planned(LandLeg $landLeg, GeneratedRoute $generatedRoute): PlannedLeg
    {
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
