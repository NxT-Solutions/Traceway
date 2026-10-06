<?php

declare(strict_types=1);

namespace App\Domain\Routing;

enum Surface: string
{
    case Paved = 'paved';
    case Mixed = 'mixed';
    case Dirt = 'dirt';
}
