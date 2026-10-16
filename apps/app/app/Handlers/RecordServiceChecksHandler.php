<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\Status\StatusRepository;
use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceProbes;
use Throwable;

final readonly class RecordServiceChecksHandler
{
    /** The status page shows this many days. Older checks are forgotten. */
    public const int KEEP_DAYS = 90;

    public function __construct(
        private ServiceProbes $serviceProbes,
        private StatusRepository $statusRepository,
    ) {}

    /**
     * @return int How many checks were saved.
     */
    public function handle(): int
    {
        $saved = 0;

        foreach ($this->serviceProbes->all() as $serviceProbe) {
            try {
                $result = $serviceProbe->check();
            } catch (Throwable) {
                $result = ProbeResult::down(0);
            }

            $this->statusRepository->recordCheck($serviceProbe->component()->value, $result->state->value, $result->milliseconds, now()->toDateTimeImmutable());
            $saved++;
        }

        $this->statusRepository->forgetChecksBefore(now()->subDays(self::KEEP_DAYS)->startOfDay()->toDateTimeImmutable());

        return $saved;
    }
}
