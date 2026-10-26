<?php

declare(strict_types=1);

namespace App\Routes;

use RuntimeException;

final class MissingAvoidedStretch extends RuntimeException
{
    public static function id(string $id): self
    {
        return new self('No avoided stretch exists for id '.$id.'.');
    }
}
