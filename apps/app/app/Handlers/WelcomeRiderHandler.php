<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Rider;
use App\Commands\WelcomeRiderCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Mail\AccountMailer;

/**
 * A rider is welcomed once, after the first confirmed email. Confirming a
 * changed address later does not send the welcome again.
 */
final readonly class WelcomeRiderHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(WelcomeRiderCommand $welcomeRiderCommand): void
    {
        $rider = $this->userRepository->markWelcomed($welcomeRiderCommand->riderId);

        if (! $rider instanceof Rider) {
            return;
        }

        $this->accountMailer->welcome($rider->name, $rider->email);
    }
}
