<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\Status\StatusRepository;
use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceProbes;
use Throwable;

final readonly class RecordServiceChecksHandler
{
    /** The status page looks back a year at most. Older checks are forgotten. */
    public const int KEEP_DAYS = 366;

    public function __construct(
        private ServiceProbes $serviceProbes,
        private StatusRepository $statusRepository,
    ) {}

    /**
     * @return int How many checks were saved.
     */
    public function handle(): int
    {
        $checks = [];

        foreach ($this->serviceProbes->all() as $serviceProbe) {
            try {
                $result = $serviceProbe->check();
            } catch (Throwable) {
                $result = ProbeResult::down(0);
            }

            $checks[] = ['component' => $serviceProbe->component()->value, 'state' => $result->state->value, 'milliseconds' => $result->milliseconds];
        }

        if ($checks !== []) {
            $this->statusRepository->recordRun($checks, now()->toDateTimeImmutable());
        }

        $this->statusRepository->forgetChecksBefore(now()->subDays(self::KEEP_DAYS)->startOfDay()->toDateTimeImmutable());

        return count($checks);
    }
}
