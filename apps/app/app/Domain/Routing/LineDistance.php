<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * Measures along a line: how far each point is from the start, where the
 * line is after so many metres, and where a nearby point meets the line.
 */
final readonly class LineDistance
{
    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<float>  $cumulative  Metres from the start to each point.
     */
    private function __construct(
        public array $line,
        public array $cumulative,
    ) {}

    /**
     * @param  list<array{0: float, 1: float}>  $line
     */
    public static function of(array $line): self
    {
        if (count($line) < 2) {
            throw new InvalidArgumentException('A line needs at least two points.');
        }

        $cumulative = [0.0];
        $travelled = 0.0;

        for ($index = 1, $count = count($line); $index < $count; $index++) {
            $travelled += Distance::metres($line[$index - 1], $line[$index]);
            $cumulative[] = $travelled;
        }

        return new self($line, $cumulative);
    }

    public function totalMetres(): float
    {
        return $this->cumulative[count($this->cumulative) - 1] ?? 0.0;
    }

    /**
     * The point on the line after so many metres from the start.
     *
     * @return array{0: float, 1: float}
     */
    public function pointAt(float $metres): array
    {
        $last = count($this->line) - 1;

        if ($metres <= 0.0) {
            return $this->line[0];
        }

        if ($metres >= $this->totalMetres()) {
            return $this->line[$last];
        }

        $low = 0;
        $high = $last;

        while ($high - $low > 1) {
            $middle = intdiv($low + $high, 2);

            if ($this->cumulative[$middle] <= $metres) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        $span = $this->cumulative[$high] - $this->cumulative[$low];
        $share = $span > 0.0 ? ($metres - $this->cumulative[$low]) / $span : 0.0;
        $from = $this->line[$low];
        $to = $this->line[$high];

        return [
            $from[0] + ($to[0] - $from[0]) * $share,
            $from[1] + ($to[1] - $from[1]) * $share,
        ];
    }

    /**
     * Where a point meets the line. With a near distance, only the stretch
     * within the window around it counts, so a road ridden twice is matched
     * on the right pass.
     *
     * @param  array{0: float, 1: float}  $point
     * @return array{metres: float, point: array{0: float, 1: float}, gap: float}
     */
    public function project(array $point, ?float $near = null, float $window = 1500.0): array
    {
        $best = $this->closest($point, $near, $window);

        if ($best === null && $near !== null) {
            $best = $this->closest($point, near: null, window: $window);
        }

        return $best ?? ['metres' => 0.0, 'point' => $this->line[0], 'gap' => Distance::metres($point, $this->line[0])];
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @return array{metres: float, point: array{0: float, 1: float}, gap: float}|null
     */
    private function closest(array $point, ?float $near, float $window): ?array
    {
        $metresPerLongitude = self::METRES_PER_DEGREE * cos(deg2rad($point[1]));
        $best = null;

        for ($index = 1, $count = count($this->line); $index < $count; $index++) {
            $startMetres = $this->cumulative[$index - 1];
            $endMetres = $this->cumulative[$index];

            if ($near !== null && ($endMetres < $near - $window || $startMetres > $near + $window)) {
                continue;
            }

            $from = $this->line[$index - 1];
            $to = $this->line[$index];
            $dx = ($to[0] - $from[0]) * $metresPerLongitude;
            $dy = ($to[1] - $from[1]) * self::METRES_PER_DEGREE;
            $px = ($point[0] - $from[0]) * $metresPerLongitude;
            $py = ($point[1] - $from[1]) * self::METRES_PER_DEGREE;
            $lengthSquared = $dx * $dx + $dy * $dy;
            $share = $lengthSquared > 0.0 ? max(0.0, min(1.0, ($px * $dx + $py * $dy) / $lengthSquared)) : 0.0;
            $gap = hypot($px - $share * $dx, $py - $share * $dy);

            if ($best === null || $gap < $best['gap']) {
                $best = [
                    'metres' => $startMetres + ($endMetres - $startMetres) * $share,
                    'point' => [
                        $from[0] + ($to[0] - $from[0]) * $share,
                        $from[1] + ($to[1] - $from[1]) * $share,
                    ],
                    'gap' => $gap,
                ];
            }
        }

        return $best;
    }
}
