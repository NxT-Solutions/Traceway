<?php

declare(strict_types=1);

namespace App\Account;

use RuntimeException;

final class MissingRider extends RuntimeException
{
    public static function id(int $id): self
    {
        return new self('No rider exists for id '.$id.'.');
    }
}
