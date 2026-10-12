<?php

declare(strict_types=1);

namespace App\Account;

use InvalidArgumentException;

/**
 * The six characters a device shows while it waits to be linked. No 0, O, 1, I, or L, so it reads cleanly on a small screen.
 */
final readonly class PairingCode
{
    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public string $value;

    public function __construct(string $value)
    {
        $code = strtoupper(str_replace([' ', '-'], '', $value));

        throw_unless(
            strlen($code) === 6 && strspn($code, self::ALPHABET) === 6,
            InvalidArgumentException::class,
            'A pairing code is six letters and digits.',
        );

        $this->value = $code;
    }

    public static function random(): self
    {
        $code = '';

        for ($index = 0; $index < 6; $index++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return new self($code);
    }
}
