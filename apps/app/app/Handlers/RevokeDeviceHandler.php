<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\RevokeDeviceCommand;
use App\Contracts\Account\DeviceLinkRepository;
use App\Contracts\Auth\CurrentRider;

final readonly class RevokeDeviceHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private DeviceLinkRepository $deviceLinkRepository,
    ) {}

    public function handle(RevokeDeviceCommand $revokeDeviceCommand): void
    {
        $this->currentRider->assertSame($revokeDeviceCommand->riderId);

        abort_unless($this->deviceLinkRepository->revoke($revokeDeviceCommand->deviceLinkId, $revokeDeviceCommand->riderId), 404);
    }
}
