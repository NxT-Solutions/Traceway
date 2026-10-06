<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use InvalidArgumentException;

/**
 * Garmin head units read the first 15 characters of a course name.
 */
final readonly class DeviceName
{
    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '' || mb_strlen($trimmed) > 15) {
            throw new InvalidArgumentException('A device name is 1 to 15 characters.');
        }

        $this->value = $trimmed;
    }
}
