<?php

declare(strict_types=1);

namespace App\Routes;

use RuntimeException;

final class ForeignRoute extends RuntimeException
{
    public static function id(string $id): self
    {
        return new self('Saved route '.$id.' belongs to another rider.');
    }
}
