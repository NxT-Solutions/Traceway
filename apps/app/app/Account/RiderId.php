<?php

declare(strict_types=1);

namespace App\Account;

use InvalidArgumentException;

final readonly class RiderId
{
    public function __construct(public int $value)
    {
        throw_if($this->value < 1, InvalidArgumentException::class, 'A rider id is a positive integer.');
    }
}
