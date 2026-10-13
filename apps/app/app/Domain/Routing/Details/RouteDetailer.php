<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

use App\Domain\Routing\LineDistance;
use App\Domain\Routing\Sport;
use InvalidArgumentException;
use Throwable;

/**
 * Puts together the details of a finished line. The road describer reads
 * heights, surfaces, and turns. The stop finder reads water, toilets, and
 * cafés. Without stops the route is still worth showing, so a stop finder
 * that does not answer leaves the stops out and nothing else. The planner
 * asks for the two apart, since the road answers in a second and the stops
 * can take longer.
 */
final readonly class RouteDetailer implements RouteDetailsSource
{
    /** A watch screen and a chart both read well under this many heights. */
    public const int MAX_PROFILE_POINTS = 1000;

    /** Two turns this close are one junction. */
    private const float CUE_GAP_METRES = 30.0;

    /** Stops of one kind this close are one stop. */
    private const float STOP_GAP_METRES = 300.0;

    /** A long route keeps about this many stops of each kind, spread along it. */
    private const int STOPS_PER_KIND = 20;

    public function __construct(
        private RoadDescriber $roadDescriber,
        private StopFinder $stopFinder,
    ) {}

    /**
     * How far off the line a stop may be. A rider covers more ground, so a
     * short detour is a longer one on foot.
     */
    public static function stopRadiusMetres(Sport $sport): int
    {
        return match ($sport) {
            Sport::Road, Sport::Gravel, Sport::Mtb => 150,
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => 75,
        };
    }

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
        if (count($line) < 2) {
            throw new InvalidArgumentException('A route needs at least two points.');
        }

        $roadDescription = $this->roadDescriber->describe($sport, $line);
        $profile = Climb::smooth($roadDescription->profile);

        return new RouteDetails(
            profile: $this->thin($profile),
            ascentMetres: Climb::ascent($profile),
            descentMetres: Climb::descent($profile),
            surfaces: $roadDescription->surfaces,
            cues: $this->cues($roadDescription->cues, LineDistance::of($line)),
            stops: [],
        );
    }

    public function stops(Sport $sport, array $line): array
    {
        if (count($line) < 2) {
            throw new InvalidArgumentException('A route needs at least two points.');
        }

        return $this->place(
            $this->stopFinder->along($sport, $line),
            LineDistance::of($line),
            self::stopRadiusMetres($sport),
        );
    }

    /**
     * @param  list<ProfilePoint>  $profile
     * @return list<ProfilePoint>
     */
    private function thin(array $profile): array
    {
        $count = count($profile);

        if ($count <= self::MAX_PROFILE_POINTS) {
            return $profile;
        }

        $step = $count / (self::MAX_PROFILE_POINTS - 1);
        $thinned = [];

        for ($index = 0; $index < self::MAX_PROFILE_POINTS - 1; $index++) {
            $thinned[] = $profile[(int) floor($index * $step)];
        }

        $thinned[] = $profile[$count - 1];

        return $thinned;
    }

    /**
     * Places each turn on this line, in order, and drops a turn right at the
     * start or the finish, or one that repeats the turn before it.
     *
     * @param  list<Cue>  $cues
     * @return list<Cue>
     */
    private function cues(array $cues, LineDistance $lineDistance): array
    {
        $total = $lineDistance->totalMetres();
        $kept = [];
        $previous = null;

        foreach ($cues as $cue) {
            $placed = $lineDistance->project([$cue->longitude, $cue->latitude], (float) $cue->distanceMetres);
            $metres = max($placed['metres'], $previous ?? 0.0);

            if ($metres < self::CUE_GAP_METRES || $metres > $total - self::CUE_GAP_METRES) {
                continue;
            }

            if ($previous !== null && $metres - $previous < self::CUE_GAP_METRES) {
                continue;
            }

            $kept[] = new Cue((int) round($metres), $cue->longitude, $cue->latitude, $cue->turn, $cue->street);
            $previous = $metres;
        }

        return $kept;
    }

    /**
     * Puts each stop on the line where the rider passes it, then keeps one of
     * each kind per stretch, so a town with six cafés does not fill the watch.
     *
     * @param  list<FoundStop>  $found
     * @return list<Stop>
     */
    private function place(array $found, LineDistance $lineDistance, int $radiusMetres): array
    {
        $placed = [];

        foreach ($found as $foundStop) {
            $projected = $lineDistance->project([$foundStop->longitude, $foundStop->latitude]);

            if ($projected['gap'] > $radiusMetres) {
                continue;
            }

            $placed[] = new Stop(
                (int) round($projected['metres']),
                $projected['point'][0],
                $projected['point'][1],
                $foundStop->kind,
                $foundStop->name,
            );
        }

        usort($placed, fn (Stop $a, Stop $b): int => $a->distanceMetres <=> $b->distanceMetres);

        $gap = max(self::STOP_GAP_METRES, $lineDistance->totalMetres() / self::STOPS_PER_KIND);
        $last = [];
        $kept = [];

        foreach ($placed as $stop) {
            $previous = $last[$stop->kind->value] ?? null;

            if ($previous !== null && $stop->distanceMetres - $previous < $gap) {
                continue;
            }

            $kept[] = $stop;
            $last[$stop->kind->value] = $stop->distanceMetres;
        }

        return $kept;
    }
}
