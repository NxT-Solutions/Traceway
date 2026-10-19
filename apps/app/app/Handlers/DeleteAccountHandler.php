<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\AccountCloser;
use App\Commands\DeleteAccountCommand;
use App\Contracts\Auth\CurrentRider;

final readonly class DeleteAccountHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private AccountCloser $accountCloser,
    ) {}

    public function handle(DeleteAccountCommand $deleteAccountCommand): void
    {
        $this->currentRider->assertSame($deleteAccountCommand->riderId);

        $this->accountCloser->close($deleteAccountCommand->riderId);
    }
}
