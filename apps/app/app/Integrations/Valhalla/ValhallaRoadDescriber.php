<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla;

use App\Domain\Routing\Details\Cue;
use App\Domain\Routing\Details\ProfilePoint;
use App\Domain\Routing\Details\RoadDescriber;
use App\Domain\Routing\Details\RoadDescription;
use App\Domain\Routing\Details\RoadSurface;
use App\Domain\Routing\Details\SurfaceShare;
use App\Domain\Routing\Details\Turn;
use App\Domain\Routing\Distance;
use App\Domain\Routing\LineSimplifier;
use App\Domain\Routing\Sport;
use App\Domain\Routing\ValhallaCosting;
use App\Integrations\Valhalla\Requests\HeightRequest;
use App\Integrations\Valhalla\Requests\TraceAttributesRequest;
use App\Integrations\Valhalla\Requests\TraceRouteRequest;
use RuntimeException;
use Saloon\Config;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Reads a finished line back from Valhalla: the turns from trace_route, the
 * surfaces from trace_attributes, and the heights from height. A long line
 * goes out in pieces, since the public server only matches so much at once.
 */
final readonly class ValhallaRoadDescriber implements RoadDescriber
{
    /**
     * @param  int  $maxPieceMetres  The public server matches at most 200 km in one request.
     * @param  int  $maxPiecePoints  And at most a few thousand points.
     * @param  float  $maxGapMetres  A simplified line can skip a long straight road in one hop. Map matching breaks on a gap that wide, so the hop gets points along it.
     * @param  int  $retryPauseMilliseconds  How long to wait before asking again for what a busy server turned away.
     */
    public function __construct(
        private ValhallaConnector $valhallaConnector,
        private int $maxPieceMetres = 100_000,
        private int $maxPiecePoints = 4_000,
        private float $maxGapMetres = 400.0,
        private int $retryPauseMilliseconds = 1_000,
    ) {}

    public function describe(Sport $sport, array $line): RoadDescription
    {
        $pieces = $this->pieces($line);
        $total = 0.0;

        foreach ($pieces as $piece) {
            $total += $piece['metres'];
        }

        $sampleMetres = max(30, (int) ceil($total / 1500));
        $costing = ValhallaCosting::trace($sport);
        $requests = [];

        foreach ($pieces as $index => $piece) {
            $shape = Polyline::encode($piece['line']);
            $match = [
                'encoded_polyline' => $shape,
                'shape_format' => 'polyline6',
                'costing' => $costing['costing'],
                'costing_options' => $costing['costing_options'],
                'shape_match' => 'map_snap',
                'trace_options' => ['search_radius' => 25],
            ];

            $requests['route:'.$index] = new TraceRouteRequest($match + [
                'directions_options' => ['units' => 'kilometers', 'directions_type' => 'maneuvers'],
            ]);
            $requests['surface:'.$index] = new TraceAttributesRequest($match + [
                'filters' => [
                    'attributes' => ['edge.surface', 'edge.length'],
                    'action' => 'include',
                ],
            ]);
            $requests['height:'.$index] = new HeightRequest([
                'encoded_polyline' => $shape,
                'shape_format' => 'polyline6',
                'range' => true,
                'resample_distance' => $sampleMetres,
            ]);
        }

        [$responses, $failures] = $this->send($requests);

        // The public server turns a busy caller away, or drops the line for
        // a moment. What it turned away is asked for once more, a second
        // later and fewer at a time, before a part is left out.
        $again = array_intersect_key($requests, array_filter($failures, $this->passing(...)));

        if ($again !== []) {
            Config::sleep($this->retryPauseMilliseconds * 1_000);
            [$retried, $stillFailing] = $this->send($again, concurrency: 2);
            $responses = $retried + $responses;
            $failures = array_diff_key($failures, $retried) + $stillFailing;
        }

        $profile = [];
        $surfaceMetres = [];
        $cues = [];
        $answered = false;
        $complete = true;

        foreach ($pieces as $index => $piece) {
            $heights = $this->heights($responses['height:'.$index] ?? null, $piece['offset'], $index > 0);
            $edges = $this->surfaces($responses['surface:'.$index] ?? null);
            $turns = $this->cues($responses['route:'.$index] ?? null, $piece['offset']);

            $answered = $answered || $heights !== null || $edges !== null || $turns !== null;
            $complete = $complete && $heights !== null && $edges !== null && $turns !== null;
            $profile = array_merge($profile, $heights ?? []);
            $cues = array_merge($cues, $turns ?? []);

            foreach ($edges ?? [RoadSurface::Unknown->value => $piece['metres']] as $surface => $metres) {
                $surfaceMetres[$surface] = ($surfaceMetres[$surface] ?? 0.0) + $metres;
            }
        }

        throw_unless($answered, RuntimeException::class, 'The router could not describe that line ('.$this->why($failures).').');

        return new RoadDescription($profile, $this->shares($surfaceMetres), $cues, $complete);
    }

    /**
     * Sends the requests together. The answers come back by key, and so does
     * why each one that failed did: its status and Valhalla's error code, or
     * that the server could not be reached.
     *
     * @param  array<string, Request>  $requests
     * @return array{0: array<string, Response>, 1: array<string, string>}
     */
    private function send(array $requests, int $concurrency = 6): array
    {
        $responses = [];
        $failures = [];

        $this->valhallaConnector->pool(
            requests: $requests,
            concurrency: min(count($requests), $concurrency),
            responseHandler: function (Response $response, int|string $key) use (&$responses, &$failures): void {
                if ($response->successful()) {
                    $responses[(string) $key] = $response;

                    return;
                }

                $failures[(string) $key] = $this->status($response);
            },
            exceptionHandler: function (mixed $reason, int|string $key) use (&$failures): void {
                $failures[(string) $key] = $reason instanceof RequestException
                    ? $this->status($reason->getResponse())
                    : 'unreachable';
            },
        )->send()->wait();

        // A request the pool never settled is as good as unanswered.
        foreach (array_keys($requests) as $key) {
            if (! isset($responses[$key]) && ! isset($failures[$key])) {
                $failures[$key] = 'unreachable';
            }
        }

        return [$responses, $failures];
    }

    /** The status of a refused request, with Valhalla's own error code when it gave one. */
    private function status(Response $response): string
    {
        $code = $response->json('error_code');

        return is_int($code) ? $response->status().'/'.$code : (string) $response->status();
    }

    /** A refusal that may pass: the server busy, broken for a moment, or not reached. */
    private function passing(string $failure): bool
    {
        $status = (int) $failure;

        return $failure === 'unreachable' || $status === 429 || $status >= 500;
    }

    /**
     * Why nothing came back, one reason per kind of request, for the error
     * report: "route 429, surface 429, height unreachable".
     *
     * @param  array<string, string>  $failures
     */
    private function why(array $failures): string
    {
        $reasons = [];

        foreach ($failures as $key => $failure) {
            $kind = explode(':', $key)[0];
            $reasons[$kind][$failure] = true;
        }

        if ($reasons === []) {
            return 'no answer';
        }

        return implode(', ', array_map(
            fn (string $kind, array $failures): string => $kind.' '.implode('/', array_keys($failures)),
            array_keys($reasons),
            $reasons,
        ));
    }

    /**
     * Splits the line under the server's limits. Each piece starts where the
     * last one ended, so the pieces cover the line once.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{line: list<array{0: float, 1: float}>, offset: float, metres: float}>
     */
    private function pieces(array $line): array
    {
        $dense = LineSimplifier::densify($line, $this->maxGapMetres);
        $first = $dense[0] ?? null;

        if ($first === null) {
            return [];
        }

        $pieces = [];
        $piece = [$first];
        $offset = 0.0;
        $metres = 0.0;

        foreach (array_slice($dense, 1) as $point) {
            $last = $piece[count($piece) - 1];
            $hop = Distance::metres($last, $point);

            if (count($piece) >= 2 && ($metres + $hop > $this->maxPieceMetres || count($piece) >= $this->maxPiecePoints)) {
                $pieces[] = ['line' => $piece, 'offset' => $offset, 'metres' => $metres];
                $offset += $metres;
                $piece = [$last];
                $metres = 0.0;
            }

            $piece[] = $point;
            $metres += $hop;
        }

        $pieces[] = ['line' => $piece, 'offset' => $offset, 'metres' => $metres];

        return $pieces;
    }

    /**
     * @return list<ProfilePoint>|null
     */
    private function heights(?Response $response, float $offset, bool $skipFirst): ?array
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return null;
        }

        $range = $response->json('range_height');

        if (! is_array($range)) {
            return null;
        }

        $profile = [];

        foreach (array_values($range) as $index => $sample) {
            if ($skipFirst && $index === 0) {
                continue;
            }

            if (! is_array($sample) || ! is_numeric($sample[0] ?? null) || ! is_numeric($sample[1] ?? null)) {
                continue;
            }

            $profile[] = new ProfilePoint((int) round($offset + (float) $sample[0]), (float) $sample[1]);
        }

        return $profile;
    }

    /**
     * @return array<string, float>|null Metres on each surface.
     */
    private function surfaces(?Response $response): ?array
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return null;
        }

        $edges = $response->json('edges');

        if (! is_array($edges)) {
            return null;
        }

        $metres = [];

        foreach ($edges as $edge) {
            if (! is_array($edge) || ! is_numeric($edge['length'] ?? null)) {
                continue;
            }

            $surface = $edge['surface'] ?? null;
            $key = RoadSurface::fromValhalla(is_string($surface) ? $surface : null)->value;
            $metres[$key] = ($metres[$key] ?? 0.0) + ((float) $edge['length']) * 1000;
        }

        return $metres;
    }

    /**
     * @return list<Cue>|null
     */
    private function cues(?Response $response, float $offset): ?array
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return null;
        }

        $legs = $response->json('trip.legs');

        if (! is_array($legs)) {
            return null;
        }

        $cues = [];
        $travelled = $offset;

        foreach ($legs as $leg) {
            if (! is_array($leg)) {
                continue;
            }

            $shape = $leg['shape'] ?? null;
            $maneuvers = $leg['maneuvers'] ?? null;

            if (! is_string($shape) || ! is_array($maneuvers)) {
                continue;
            }

            $points = Polyline::decode($shape);

            foreach ($maneuvers as $maneuver) {
                if (! is_array($maneuver)) {
                    continue;
                }

                $type = $maneuver['type'] ?? null;
                $index = $maneuver['begin_shape_index'] ?? null;
                $turn = is_int($type) ? Turn::fromValhalla($type) : null;
                $point = is_int($index) ? ($points[$index] ?? null) : null;

                if ($turn instanceof Turn && $point !== null) {
                    $cues[] = new Cue((int) round($travelled), $point[0], $point[1], $turn, $this->street($maneuver, $turn));
                }

                $length = $maneuver['length'] ?? 0;
                $travelled += is_numeric($length) ? ((float) $length) * 1000 : 0.0;
            }
        }

        return $cues;
    }

    /**
     * @param  array<mixed>  $maneuver
     */
    private function street(array $maneuver, Turn $turn): ?string
    {
        if ($turn === Turn::Straight) {
            $exit = $maneuver['roundabout_exit_count'] ?? null;

            return is_int($exit) && $exit > 0 ? 'Exit '.$exit : null;
        }

        $names = $maneuver['street_names'] ?? null;
        $name = is_array($names) ? ($names[0] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, float>  $surfaceMetres
     * @return list<SurfaceShare>
     */
    private function shares(array $surfaceMetres): array
    {
        $shares = [];

        foreach (RoadSurface::cases() as $roadSurface) {
            $metres = (int) round($surfaceMetres[$roadSurface->value] ?? 0.0);

            if ($metres > 0) {
                $shares[] = new SurfaceShare($roadSurface, $metres);
            }
        }

        return $shares;
    }
}
