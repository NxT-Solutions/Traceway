<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\DisconnectAppCommand;
use App\Contracts\Account\ConnectedAppRepository;
use App\Contracts\Auth\CurrentRider;

final readonly class DisconnectAppHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private ConnectedAppRepository $connectedAppRepository,
    ) {}

    public function handle(DisconnectAppCommand $disconnectAppCommand): void
    {
        $this->currentRider->assertSame($disconnectAppCommand->riderId);

        $this->connectedAppRepository->disconnect($disconnectAppCommand->riderId, $disconnectAppCommand->clientId);
    }
}
