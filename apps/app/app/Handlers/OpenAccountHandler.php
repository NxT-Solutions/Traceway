<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Rider;
use App\Commands\OpenAccountCommand;
use App\Contracts\Account\UserRepository;

/**
 * Opens the account. Fortify sends the confirmation link. The welcome waits
 * until the email is confirmed.
 */
final readonly class OpenAccountHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(OpenAccountCommand $openAccountCommand): Rider
    {
        return $this->userRepository->create($openAccountCommand->name, $openAccountCommand->email, $openAccountCommand->password);
    }
}
