<?php

declare(strict_types=1);

namespace App\Domain\Status;

/**
 * The headings on the status page, in the order they are shown.
 */
enum StatusGroup: string
{
    case Traceway = 'traceway';
    case Maps = 'maps';
    case Apps = 'apps';
    case Devices = 'devices';

    public function label(): string
    {
        return match ($this) {
            self::Traceway => 'Traceway',
            self::Maps => 'Map services',
            self::Apps => 'Send to apps',
            self::Devices => 'Devices',
        };
    }
}
