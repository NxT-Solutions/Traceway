<?php

declare(strict_types=1);

namespace App\Account;

use InvalidArgumentException;

final readonly class DeviceLabel
{
    public string $value;

    public function __construct(string $value)
    {
        $label = trim($value);

        throw_if($label === '' || mb_strlen($label) > 40, InvalidArgumentException::class, 'A device label is 1 to 40 characters.');

        $this->value = $label;
    }
}
