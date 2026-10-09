<?php

declare(strict_types=1);

namespace App\Routes;

use RuntimeException;

final class MissingRoute extends RuntimeException
{
    public static function id(string $id): self
    {
        return new self('No saved route exists for id '.$id.'.');
    }
}
