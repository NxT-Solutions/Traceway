<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\AdminAction;
use App\Account\RiderId;
use App\Contracts\Account\AdminLog;
use App\Contracts\Auth\Impersonator;

final readonly class StopImpersonationHandler
{
    public function __construct(
        private Impersonator $impersonator,
        private AdminLog $adminLog,
    ) {}

    /**
     * @return RiderId|null The rider the Super Admin acted as, or null when nobody was acting as anyone.
     */
    public function handle(): ?RiderId
    {
        $stopped = $this->impersonator->stop();

        if ($stopped === null) {
            return null;
        }

        $this->adminLog->record($stopped['actor'], AdminAction::ImpersonationStopped, $stopped['rider']);

        return $stopped['rider'];
    }
}
