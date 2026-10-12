<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\PairingState;
use App\Commands\ClaimDevicePairingCommand;
use App\Contracts\Account\DeviceLinkRepository;
use Illuminate\Support\Str;

final readonly class ClaimDevicePairingHandler
{
    public function __construct(
        private DeviceLinkRepository $deviceLinkRepository,
    ) {}

    /**
     * The device token once a rider has entered the code, or null while it still waits.
     */
    public function handle(ClaimDevicePairingCommand $claimDevicePairingCommand): ?string
    {
        $token = Str::random(64);

        $pairingState = $this->deviceLinkRepository->claim(
            hash('sha256', $claimDevicePairingCommand->secret),
            hash('sha256', $token),
        );

        return match ($pairingState) {
            PairingState::Linked => $token,
            PairingState::Waiting => null,
            PairingState::Gone => abort(410, 'This pairing is closed. Start again on the device.'),
        };
    }
}
