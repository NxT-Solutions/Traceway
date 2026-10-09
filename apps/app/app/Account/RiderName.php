<?php

declare(strict_types=1);

namespace App\Account;

use InvalidArgumentException;

final readonly class RiderName
{
    public string $value;

    public function __construct(string $value)
    {
        $name = trim($value);

        throw_if($name === '' || mb_strlen($name) > 255, InvalidArgumentException::class, 'A rider name is 1 to 255 characters.');

        $this->value = $name;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
