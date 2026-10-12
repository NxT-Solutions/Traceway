<?php

declare(strict_types=1);

namespace App\Account;

/**
 * Where a device stands after it asks for its token.
 */
enum PairingState
{
    /** No rider has entered the code yet. */
    case Waiting;

    /** A rider entered the code, and the token was issued with this answer. */
    case Linked;

    /** The code ran out, or the token was already handed over. */
    case Gone;
}
