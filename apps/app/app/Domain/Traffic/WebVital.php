<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * A Core Web Vital, with Google's limits for good and poor at the 75th percentile.
 */
enum WebVital: string
{
    /** Largest Contentful Paint, in milliseconds. */
    case Lcp = 'lcp';

    /** Interaction to Next Paint, in milliseconds. */
    case Inp = 'inp';

    /** Cumulative Layout Shift, without a unit. */
    case Cls = 'cls';

    public function rating(float $value): VitalRating
    {
        [$good, $poor] = match ($this) {
            self::Lcp => [2500.0, 4000.0],
            self::Inp => [200.0, 500.0],
            self::Cls => [0.1, 0.25],
        };

        return match (true) {
            $value <= $good => VitalRating::Good,
            $value <= $poor => VitalRating::NeedsWork,
            default => VitalRating::Poor,
        };
    }
}
