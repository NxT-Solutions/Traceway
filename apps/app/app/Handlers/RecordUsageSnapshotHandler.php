<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\Usage\UsageCounter;
use App\Domain\Usage\UsageMetric;

final readonly class RecordUsageSnapshotHandler
{
    /** The admin area shows two years. Older totals are forgotten. */
    public const int KEEP_DAYS = 730;

    public function __construct(private UsageCounter $usageCounter) {}

    /**
     * @return int How many old totals were forgotten.
     */
    public function handle(): int
    {
        $now = now()->toImmutable();

        foreach (UsageMetric::cases() as $usageMetric) {
            $days = $usageMetric->activeDays();

            if ($days !== null) {
                $this->usageCounter->set($usageMetric->value, $now->toDateTimeImmutable(), $this->usageCounter->ridersSeenSince($now->subDays($days)->toDateTimeImmutable()));
            }
        }

        return $this->usageCounter->forgetBefore($now->subDays(self::KEEP_DAYS)->startOfDay()->toDateTimeImmutable());
    }
}
