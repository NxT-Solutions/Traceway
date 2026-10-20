<?php

declare(strict_types=1);

namespace App\Account;

/**
 * What opening the link sent to a new email did.
 */
enum EmailChangeOutcome: string
{
    /** The account signs in with the new email now. */
    case Changed = 'changed';

    /** The change was cancelled, or replaced by a newer one, so the link no longer counts. */
    case Stale = 'stale';

    /** Another account took the email in the meantime. */
    case Taken = 'taken';
}
