<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\Rider;
use App\Commands\OpenAccountWithoutConnectionCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Mail\AccountMailer;

/**
 * Opens the account without a password, with the email confirmed by Traceway's link, and welcomes the rider. They
 * set a password, or connect a service, from Settings.
 */
final readonly class OpenAccountWithoutConnectionHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(OpenAccountWithoutConnectionCommand $openAccountWithoutConnectionCommand): Rider
    {
        if ($this->userRepository->findByEmail($openAccountWithoutConnectionCommand->email) instanceof Rider) {
            throw ConnectionFailed::because($openAccountWithoutConnectionCommand->connectionProvider, ConnectionProblem::EmailTaken);
        }

        $rider = $this->userRepository->createWithoutPassword($openAccountWithoutConnectionCommand->name, $openAccountWithoutConnectionCommand->email, emailConfirmed: true);
        $welcomed = $this->userRepository->markWelcomed($rider->id);

        if ($welcomed instanceof Rider) {
            $this->accountMailer->welcome($welcomed->name, $welcomed->email);
        }

        return $rider;
    }
}
