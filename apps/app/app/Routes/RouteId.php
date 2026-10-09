<?php

declare(strict_types=1);

namespace App\Routes;

use InvalidArgumentException;

final readonly class RouteId
{
    public function __construct(public string $value)
    {
        throw_if(preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $this->value) !== 1, InvalidArgumentException::class, 'A route id is a UUID.');
    }
}
