<?php

declare(strict_types=1);

namespace App\Routes;

use RuntimeException;

final class ForeignAvoidedStretch extends RuntimeException
{
    public static function id(string $id): self
    {
        return new self('Avoided stretch '.$id.' belongs to another rider.');
    }
}
