<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Rider;
use App\Commands\OpenAccountCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Mail\AccountMailer;

final readonly class OpenAccountHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(OpenAccountCommand $openAccountCommand): Rider
    {
        $rider = $this->userRepository->create($openAccountCommand->name, $openAccountCommand->email, $openAccountCommand->password);
        $this->accountMailer->welcome($rider->name, $rider->email);

        return $rider;
    }
}
