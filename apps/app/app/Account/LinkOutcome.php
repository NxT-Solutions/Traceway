<?php

declare(strict_types=1);

namespace App\Account;

/**
 * What linking an account at a service changed.
 */
enum LinkOutcome: string
{
    /** The rider had no connection to this service before. */
    case Linked = 'linked';

    /** The same account again, with fresh details and keys. */
    case Refreshed = 'refreshed';

    /**
     * A connection carried over from before, which had no number from the service and so could not log the rider
     * in, got its number. It keeps when it was made and the routes sent through it.
     */
    case Claimed = 'claimed';

    /** Another account at the same service took the place of the old one. */
    case Replaced = 'replaced';

    /**
     * True when the rider can now log in with an account they could not log in with before.
     */
    public function addsSignIn(): bool
    {
        return $this !== self::Refreshed;
    }

    /**
     * True when the connection was there before and goes on with the new keys, so the keys it held are let go.
     * A replaced account's access is withdrawn in full instead.
     */
    public function supersedesKeys(): bool
    {
        return $this === self::Refreshed || $this === self::Claimed;
    }
}
