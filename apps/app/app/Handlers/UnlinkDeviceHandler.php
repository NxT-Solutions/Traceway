<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\UnlinkDeviceCommand;
use App\Contracts\Account\DeviceLinkRepository;

final readonly class UnlinkDeviceHandler
{
    public function __construct(
        private DeviceLinkRepository $deviceLinkRepository,
    ) {}

    public function handle(UnlinkDeviceCommand $unlinkDeviceCommand): void
    {
        $this->deviceLinkRepository->revokeToken($unlinkDeviceCommand->tokenHash);
    }
}
