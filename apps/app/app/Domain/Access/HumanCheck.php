<?php

declare(strict_types=1);

namespace App\Domain\Access;

use RuntimeException;

/**
 * Tells a person from a script on the forms that create an account or send
 * an email, so a bot cannot fill them by the thousand.
 */
interface HumanCheck
{
    /**
     * Whether the token the form sent shows a person filled it.
     *
     * @throws RuntimeException when the check could not be asked
     */
    public function passes(string $token, string $ip): bool;
}
