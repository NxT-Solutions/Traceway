<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\Routing\Distance;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps a line to what a rider could plan or bring in. A longer one asks
 * the map servers for many pieces at once, which they turn away.
 */
final readonly class LineWithinReach implements ValidationRule
{
    /**
     * The longest line any endpoint takes: a 300 km loop or opened file,
     * the longest the planner makes, with room for one that lands long or
     * is started from a pin away from it.
     */
    public const int METRES = 400_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $line = [];

        foreach ($value as $point) {
            if (is_array($point) && is_numeric($point[0] ?? null) && is_numeric($point[1] ?? null)) {
                $line[] = [(float) $point[0], (float) $point[1]];
            }
        }

        if (Distance::along($line) > self::METRES) {
            $fail('A route can be up to '.number_format(self::METRES / 1000).' km.');
        }
    }
}
