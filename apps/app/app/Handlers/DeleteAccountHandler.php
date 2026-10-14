<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\DeleteAccountCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Routes\RouteImageStore;

final readonly class DeleteAccountHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
        private RouteImageStore $routeImageStore,
    ) {}

    public function handle(DeleteAccountCommand $deleteAccountCommand): void
    {
        $this->currentRider->assertSame($deleteAccountCommand->riderId);

        $rider = $this->userRepository->delete($deleteAccountCommand->riderId);
        $this->routeImageStore->forgetRider($deleteAccountCommand->riderId);
        $this->accountMailer->accountDeleted($rider->name, $rider->email);
    }
}
