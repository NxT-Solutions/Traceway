<?php

declare(strict_types=1);

namespace App\Routes;

use RuntimeException;

final class TooManyAvoidedStretches extends RuntimeException
{
    /** The most stretches one rider keeps. */
    public const int MOST = 200;

    public static function limit(): self
    {
        return new self('A rider avoids at most '.self::MOST.' stretches.');
    }
}
