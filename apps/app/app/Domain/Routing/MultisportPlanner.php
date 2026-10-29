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
    /**
     * How far round the directions are turned for a second try. The engine
     * tries six, 60° apart, and then the ones 30° round; 15° are new to it.
     */
    private const int RETRY_TURN_DEGREES = 15;

    public function __construct(private RoutingEngine $routingEngine) {}

    public function plan(MultisportQuery $multisportQuery): MultisportRoute
    {
        $legs = $multisportQuery->swim instanceof SwimLeg ? [PlannedLeg::swim($multisportQuery->swim)] : [];
        $from = $multisportQuery->transition();
        $ridden = [];

        foreach ($multisportQuery->legs as $position => $landLeg) {
            if ($multisportQuery->layout === Layout::Loops) {
                $leg = $this->loop($landLeg, $from, $position, $ridden, $multisportQuery->avoid);
            } else {
                /** @var array{0: float, 1: float} $finish */
                $finish = $landLeg->finish;
                $leg = $this->handover($landLeg, $from, $finish, $ridden, $multisportQuery->avoid);
                $from = $finish;
            }

            $legs[] = $leg;
            $ridden = $ridden === [] ? $leg->coordinates : array_merge($ridden, array_slice($leg->coordinates, 1));
        }

        return new MultisportRoute($multisportQuery->layout, $legs);
    }

    /**
     * Of the loops the engine offers, the best by the same rules as any loop,
     * keeping off the roads the earlier legs rode. The engine picks its loops
     * without knowing those legs, so when every loop it offers is flawed, by
     * their roads or by its shape, other directions are tried once before the
     * leg settles.
     *
     * @param  array{0: float, 1: float}  $transition
     * @param  list<array{0: float, 1: float}>  $ridden
     * @param  list<AvoidedStretch>  $avoid
     */
    private function loop(LandLeg $landLeg, array $transition, int $position, array $ridden, array $avoid): PlannedLeg
    {
        $found = $this->routingEngine->candidates($this->query($landLeg, Shape::Loop, $transition, $avoid));
        $best = CleanLeg::best($ridden, $found, $landLeg->distanceMetres);

        if ($best instanceof GeneratedRoute && RouteCleanliness::flawed($best, $ridden)) {
            try {
                $found = [...$found, ...$this->routingEngine->candidates($this->query($landLeg, Shape::Loop, $transition, $avoid, self::RETRY_TURN_DEGREES))];
                $best = CleanLeg::best($ridden, $found, $landLeg->distanceMetres);
            } catch (RuntimeException) {
                // Nothing more came back, or the server is busy: the leg keeps what it has.
            }
        }

        $best ??= throw new RuntimeException(sprintf('No loop came back for leg %d.', $position + 1));

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
     * @param  list<AvoidedStretch>  $avoid
     */
    private function handover(LandLeg $landLeg, array $from, array $to, array $ridden, array $avoid): PlannedLeg
    {
        $routeQuery = $this->query($landLeg, Shape::PointToPoint, $from, $avoid);
        $generatedRoute = $this->routingEngine->trace($routeQuery, [$from, $to]);
        $stretch = $landLeg->stretch && $generatedRoute->distanceMetres < $landLeg->distanceMetres * 0.9;
        $target = $stretch ? $landLeg->distanceMetres : $generatedRoute->distanceMetres;
        $doubles = CleanLeg::doublesBack($ridden, $generatedRoute);

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

        if (CleanLeg::doublesBack($ridden, $best)) {
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
     * @param  list<AvoidedStretch>  $avoid
     */
    private function query(LandLeg $landLeg, Shape $shape, array $start, array $avoid, int $turnDegrees = 0): RouteQuery
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
            avoid: AvoidedStretches::forSport($avoid, $landLeg->sport),
            turnDegrees: $turnDegrees,
        );
    }
}
