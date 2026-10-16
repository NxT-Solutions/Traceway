<?php

declare(strict_types=1);

namespace App\Domain\Status;

/**
 * How a part of Traceway answered its last check.
 */
enum ServiceState: string
{
    case Up = 'up';

    /** Not a fault: the Garmin store has not approved the app yet. */
    case Waiting = 'waiting';

    /** It answered, but took longer than a rider would wait. */
    case Slow = 'slow';

    case Down = 'down';

    /**
     * Higher is worse. A day shows the worst state it had.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Up => 0,
            self::Waiting => 1,
            self::Slow => 2,
            self::Down => 3,
        };
    }

    public function worseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }
}
