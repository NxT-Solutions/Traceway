<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\DevicePairing;
use App\Account\PairingCode;
use App\Commands\StartDevicePairingCommand;
use App\Contracts\Account\DeviceLinkRepository;
use Illuminate\Support\Str;

final readonly class StartDevicePairingHandler
{
    public function __construct(
        private DeviceLinkRepository $deviceLinkRepository,
    ) {}

    public function handle(StartDevicePairingCommand $startDevicePairingCommand): DevicePairing
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $pairingCode = PairingCode::random();
            $secret = Str::random(48);

            if ($this->deviceLinkRepository->open($startDevicePairingCommand->provider, $pairingCode, hash('sha256', $secret), DevicePairing::SECONDS)) {
                return new DevicePairing($pairingCode, $secret);
            }
        }

        abort(503, 'No pairing code is free. Try again.');
    }
}
