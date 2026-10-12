<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\LinkDeviceCommand;
use App\Contracts\Account\DeviceLinkRepository;
use App\Contracts\Auth\CurrentRider;
use Illuminate\Validation\ValidationException;

final readonly class LinkDeviceHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private DeviceLinkRepository $deviceLinkRepository,
    ) {}

    public function handle(LinkDeviceCommand $linkDeviceCommand): void
    {
        $this->currentRider->assertSame($linkDeviceCommand->riderId);

        $linked = $this->deviceLinkRepository->link(
            $linkDeviceCommand->code,
            $linkDeviceCommand->riderId,
            $linkDeviceCommand->label,
        );

        if (! $linked) {
            throw ValidationException::withMessages([
                'code' => 'That code is not waiting. Open Traceway on the device for a new one.',
            ]);
        }
    }
}
