<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * Google's three ratings of a Core Web Vital.
 */
enum VitalRating: string
{
    case Good = 'good';
    case NeedsWork = 'needs_work';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::NeedsWork => 'Needs work',
            self::Poor => 'Poor',
        };
    }
}
