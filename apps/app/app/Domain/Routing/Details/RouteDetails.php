<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

use InvalidArgumentException;

/**
 * What a finished route is like: how it climbs, what it runs on, where it
 * turns, and where to stop for water, a toilet, or a coffee.
 */
final readonly class RouteDetails
{
    /**
     * @param  list<ProfilePoint>  $profile
     * @param  list<SurfaceShare>  $surfaces
     * @param  list<Cue>  $cues
     * @param  list<Stop>  $stops
     */
    public function __construct(
        public array $profile,
        public int $ascentMetres,
        public int $descentMetres,
        public array $surfaces,
        public array $cues,
        public array $stops,
        // False when the map server left part of the line out. Such details
        // are shown, not remembered, so the next read can fill the gap.
        public bool $complete = true,
    ) {}

    /**
     * Whether the heights came back. Without them the climb is unknown, not
     * flat, so a climb from elsewhere stands.
     */
    public function hasClimb(): bool
    {
        return $this->profile !== [];
    }

    /**
     * The same details with these stops.
     *
     * @param  list<Stop>  $stops
     */
    public function withStops(array $stops): self
    {
        return new self($this->profile, $this->ascentMetres, $this->descentMetres, $this->surfaces, $this->cues, $stops, $this->complete);
    }

    /**
     * The height of the ground after so many metres, between the two samples
     * either side. Null when the profile is empty.
     */
    public function elevationAt(float $metres): ?float
    {
        $count = count($this->profile);

        if ($count === 0) {
            return null;
        }

        $first = $this->profile[0];
        $last = $this->profile[$count - 1];

        if ($metres <= $first->distanceMetres) {
            return $first->elevationMetres;
        }

        if ($metres >= $last->distanceMetres) {
            return $last->elevationMetres;
        }

        $low = 0;
        $high = $count - 1;

        while ($high - $low > 1) {
            $middle = intdiv($low + $high, 2);

            if ($this->profile[$middle]->distanceMetres <= $metres) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        $from = $this->profile[$low];
        $to = $this->profile[$high];
        $span = $to->distanceMetres - $from->distanceMetres;
        $share = $span > 0 ? ($metres - $from->distanceMetres) / $span : 0.0;

        return $from->elevationMetres + ($to->elevationMetres - $from->elevationMetres) * $share;
    }

    /**
     * @return array{profile: list<array{0: int, 1: float}>, ascent_metres: int, descent_metres: int, surfaces: list<array{surface: string, metres: int}>, cues: list<array{distance_metres: int, longitude: float, latitude: float, turn: string, street: string|null}>, stops: list<array{distance_metres: int, longitude: float, latitude: float, kind: string, name: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'profile' => array_map(
                fn (ProfilePoint $profilePoint): array => [$profilePoint->distanceMetres, round($profilePoint->elevationMetres, 1)],
                $this->profile,
            ),
            'ascent_metres' => $this->ascentMetres,
            'descent_metres' => $this->descentMetres,
            'surfaces' => array_map(
                fn (SurfaceShare $surfaceShare): array => ['surface' => $surfaceShare->surface->value, 'metres' => $surfaceShare->metres],
                $this->surfaces,
            ),
            'cues' => array_map(
                fn (Cue $cue): array => [
                    'distance_metres' => $cue->distanceMetres,
                    'longitude' => round($cue->longitude, 6),
                    'latitude' => round($cue->latitude, 6),
                    'turn' => $cue->turn->value,
                    'street' => $cue->street,
                ],
                $this->cues,
            ),
            'stops' => array_map(
                fn (Stop $stop): array => [
                    'distance_metres' => $stop->distanceMetres,
                    'longitude' => round($stop->longitude, 6),
                    'latitude' => round($stop->latitude, 6),
                    'kind' => $stop->kind->value,
                    'name' => $stop->name,
                ],
                $this->stops,
            ),
        ];
    }

    /**
     * Reads the details back from the array {@see toArray()} wrote.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $profile = [];

        foreach (self::list($data, 'profile') as $point) {
            if (! is_array($point) || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                throw new InvalidArgumentException('A profile point is not a pair of numbers.');
            }

            $profile[] = new ProfilePoint((int) $point[0], (float) $point[1]);
        }

        $surfaces = [];

        foreach (self::list($data, 'surfaces') as $share) {
            if (! is_array($share)) {
                throw new InvalidArgumentException('A surface share is not a record.');
            }

            $surfaces[] = new SurfaceShare(RoadSurface::from(self::text($share, 'surface')), self::whole($share, 'metres'));
        }

        $cues = [];

        foreach (self::list($data, 'cues') as $cue) {
            if (! is_array($cue)) {
                throw new InvalidArgumentException('A cue is not a record.');
            }

            $cues[] = new Cue(
                self::whole($cue, 'distance_metres'),
                self::number($cue, 'longitude'),
                self::number($cue, 'latitude'),
                Turn::from(self::text($cue, 'turn')),
                self::optionalText($cue, 'street'),
            );
        }

        $stops = [];

        foreach (self::list($data, 'stops') as $stop) {
            if (! is_array($stop)) {
                throw new InvalidArgumentException('A stop is not a record.');
            }

            $stops[] = new Stop(
                self::whole($stop, 'distance_metres'),
                self::number($stop, 'longitude'),
                self::number($stop, 'latitude'),
                StopKind::from(self::text($stop, 'kind')),
                self::optionalText($stop, 'name'),
            );
        }

        return new self(
            $profile,
            self::whole($data, 'ascent_metres'),
            self::whole($data, 'descent_metres'),
            $surfaces,
            $cues,
            $stops,
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return list<mixed>
     */
    private static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('Route details '.$key.' is not a list.');
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException('Route details '.$key.' is not text.');
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function optionalText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function whole(array $data, string $key): int
    {
        return (int) self::number($data, $key);
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function number(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Route details '.$key.' is not a number.');
        }

        return (float) $value;
    }
}
