<?php

declare(strict_types=1);

namespace App\Account;

use InvalidArgumentException;

final readonly class Email
{
    public string $value;

    public function __construct(string $value)
    {
        $email = mb_strtolower(trim($value));

        throw_if(filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255, InvalidArgumentException::class, 'An email address is required.');

        $this->value = $email;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
