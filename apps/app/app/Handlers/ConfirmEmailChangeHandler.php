<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Email;
use App\Account\EmailChangeOutcome;
use App\Account\Rider;
use App\Commands\ConfirmEmailChangeCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Mail\AccountMailer;

/**
 * Makes the new email count, confirmed, since the rider read the link sent to it. The old address is told, so a
 * change the rider did not make does not go unnoticed.
 */
final readonly class ConfirmEmailChangeHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(ConfirmEmailChangeCommand $confirmEmailChangeCommand): EmailChangeOutcome
    {
        $riderId = $confirmEmailChangeCommand->riderId;
        $email = $confirmEmailChangeCommand->email;

        $this->currentRider->assertSame($riderId);

        if ($this->userRepository->pendingEmail($riderId)?->equals($email) !== true) {
            return EmailChangeOutcome::Stale;
        }

        $owner = $this->userRepository->findByEmail($email);

        if ($owner instanceof Rider && $owner->id->value !== $riderId->value) {
            $this->userRepository->cancelEmailChange($riderId);

            return EmailChangeOutcome::Taken;
        }

        $previous = $this->userRepository->confirmEmailChange($riderId, $email);

        if (! $previous instanceof Email) {
            return EmailChangeOutcome::Stale;
        }

        $rider = $this->userRepository->find($riderId);
        $this->accountMailer->emailChanged($rider->name, $previous, $email);

        return EmailChangeOutcome::Changed;
    }
}
