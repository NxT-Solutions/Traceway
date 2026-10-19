<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * Whether the database backups are still being written to their R2 bucket: how big the bucket is, and how many
 * files went into it each day over the last 30 days.
 *
 * @phpstan-import-type DayCount from SecurityReport
 */
final readonly class BackupHealth
{
    public const int DAYS = 30;

    /**
     * @param  list<DayCount>  $writeDays  files written each day
     */
    public function __construct(
        public ?string $bucket = null,
        public int $objects = 0,
        public int $bytes = 0,
        public array $writeDays = [],
        public ?string $lastWrite = null,
    ) {}
}
