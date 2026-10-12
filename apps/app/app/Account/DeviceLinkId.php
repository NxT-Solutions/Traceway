<?php

declare(strict_types=1);

namespace App\Account;

use InvalidArgumentException;

final readonly class DeviceLinkId
{
    public function __construct(public string $value)
    {
        throw_unless(
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            InvalidArgumentException::class,
            'A device link id is a UUID.',
        );
    }
}
