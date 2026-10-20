<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\ConfirmRiderEmailCommand;
use App\Contracts\Account\UserRepository;

final readonly class ConfirmRiderEmailHandler
{
    public function __construct(private UserRepository $userRepository) {}

    public function handle(ConfirmRiderEmailCommand $confirmRiderEmailCommand): void
    {
        $this->userRepository->confirmEmail($confirmRiderEmailCommand->riderId);
    }
}
