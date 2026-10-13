<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Routing\Details\Cue;
use App\Domain\Routing\Details\ProfilePoint;
use App\Domain\Routing\Details\RoadSurface;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\Details\Stop;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Details\SurfaceShare;
use App\Domain\Routing\Details\Turn;
use App\Domain\Routing\Distance;
use App\Domain\Routing\Sport;

/**
 * Details that climb 40 metres, turn left once, and pass one water tap,
 * whatever the line.
 */
final class FixedRouteDetails implements RouteDetailsSource
{
    /** @var list<array{sport: Sport, points: int}> */
    public array $asked = [];

    public function for(Sport $sport, array $line): RouteDetails
    {
        $this->asked[] = ['sport' => $sport, 'points' => count($line)];
        $metres = (int) round(Distance::along($line));
        $middle = $line[intdiv(count($line), 2)];

        return new RouteDetails(
            profile: [new ProfilePoint(0, 10.0), new ProfilePoint(intdiv($metres, 2), 50.0), new ProfilePoint($metres, 10.0)],
            ascentMetres: 40,
            descentMetres: 40,
            surfaces: [new SurfaceShare(RoadSurface::Paved, $metres)],
            cues: [new Cue(intdiv($metres, 2), $middle[0], $middle[1], Turn::Left, 'Mill Road')],
            stops: [new Stop(intdiv($metres, 3), $middle[0], $middle[1], StopKind::Water)],
        );
    }

    public function road(Sport $sport, array $line): RouteDetails
    {
        return $this->for($sport, $line)->withStops([]);
    }

    public function stops(Sport $sport, array $line): array
    {
        return $this->for($sport, $line)->stops;
    }
}
