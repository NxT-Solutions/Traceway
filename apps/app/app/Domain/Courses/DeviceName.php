<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use App\Domain\Routing\Sport;
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

    /**
     * A name for one leg of a multisport plan, such as "Brick 2 Run". The
     * plan name is shortened so the leg number and sport always fit.
     */
    public static function forLeg(string $base, int $position, Sport $sport): self
    {
        $suffix = ' '.$position.' '.self::sportLabel($sport);
        $trimmed = trim($base) === '' ? 'Leg' : trim($base);

        return new self(rtrim(mb_substr($trimmed, 0, max(1, 15 - mb_strlen($suffix)))).$suffix);
    }

    private static function sportLabel(Sport $sport): string
    {
        return match ($sport) {
            Sport::Road => 'Road',
            Sport::Gravel => 'Gravel',
            Sport::Mtb => 'MTB',
            Sport::Run => 'Run',
            Sport::TrailRun => 'Trail',
            Sport::Walk => 'Walk',
            Sport::Hike => 'Hike',
        };
    }
}
