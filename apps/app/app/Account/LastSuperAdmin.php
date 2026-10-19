<?php

declare(strict_types=1);

namespace App\Account;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * A write would have left Traceway without a Super Admin, so it was undone.
 */
final class LastSuperAdmin extends AuthorizationException
{
    public static function wouldRemain(): self
    {
        return new self('Traceway keeps at least one Super Admin. Make someone else a Super Admin first.');
    }
}
