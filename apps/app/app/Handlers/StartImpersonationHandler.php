<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\AdminAction;
use App\Commands\StartImpersonationCommand;
use App\Contracts\Account\AdminLog;
use App\Contracts\Auth\AdminAccess;
use App\Contracts\Auth\Impersonator;

final readonly class StartImpersonationHandler
{
    public function __construct(
        private AdminAccess $adminAccess,
        private Impersonator $impersonator,
        private AdminLog $adminLog,
    ) {}

    public function handle(StartImpersonationCommand $startImpersonationCommand): void
    {
        $this->adminAccess->assertMayImpersonate($startImpersonationCommand->actorId, $startImpersonationCommand->riderId);

        $this->adminLog->record($startImpersonationCommand->actorId, AdminAction::ImpersonationStarted, $startImpersonationCommand->riderId);
        $this->impersonator->start($startImpersonationCommand->actorId, $startImpersonationCommand->riderId);
    }
}
