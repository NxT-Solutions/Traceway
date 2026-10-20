<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\Rider;
use App\Account\RiderId;
use App\Commands\OpenAccountWithConnectionCommand;
use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Mail\AccountMailer;
use Throwable;

/**
 * Opens the account without a password and connects the service to it. An email the service confirmed, or one
 * the rider confirmed through Traceway's link, is trusted, so the rider is welcomed at once. Any other email gets
 * Traceway's own confirmation link first.
 *
 * The welcome is sent here, not through the Verified event, so a service vouching for an address never makes its
 * owner a Super Admin. That still takes Traceway's own link, and the caller announces it when one was used.
 */
final readonly class OpenAccountWithConnectionHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ConnectionRepository $connectionRepository,
        private EmailConfirmation $emailConfirmation,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(OpenAccountWithConnectionCommand $openAccountWithConnectionCommand): Rider
    {
        $connectedIdentity = $openAccountWithConnectionCommand->connectedIdentity;
        $connectionProvider = $connectedIdentity->provider;

        if ($this->connectionRepository->riderFor($connectionProvider, $connectedIdentity->providerUserId) instanceof RiderId) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::TakenByAnotherRider);
        }

        if ($this->userRepository->findByEmail($openAccountWithConnectionCommand->email) instanceof Rider) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::EmailTaken);
        }

        $confirmed = $openAccountWithConnectionCommand->emailConfirmed
            || ($connectedIdentity->emailVerified && $connectedIdentity->email?->equals($openAccountWithConnectionCommand->email) === true);

        $rider = $this->userRepository->createWithoutPassword($openAccountWithConnectionCommand->name, $openAccountWithConnectionCommand->email, $confirmed);

        try {
            $this->connectionRepository->link($rider->id, $connectedIdentity);
        } catch (Throwable $throwable) {
            // Two tabs raced for the same account at the service. Without the connection the new account has no
            // way in, so it goes again.
            $this->userRepository->delete($rider->id);

            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::TakenByAnotherRider, $throwable);
        }

        if (! $confirmed) {
            $this->emailConfirmation->request($rider->id);

            return $rider;
        }

        $welcomed = $this->userRepository->markWelcomed($rider->id);

        if ($welcomed instanceof Rider) {
            $this->accountMailer->welcome($welcomed->name, $welcomed->email);
        }

        return $rider;
    }
}
