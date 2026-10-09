<?php

declare(strict_types=1);

namespace App\Routes;

use InvalidArgumentException;

final readonly class RouteName
{
    public string $value;

    public function __construct(string $value)
    {
        $name = trim($value);

        throw_if($name === '' || mb_strlen($name) > 80, InvalidArgumentException::class, 'A route name is 1 to 80 characters.');

        $this->value = $name;
    }
}
