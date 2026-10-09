<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\UpdateProfileCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;

final readonly class UpdateProfileHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
    ) {}

    public function handle(UpdateProfileCommand $updateProfileCommand): void
    {
        $this->currentRider->assertSame($updateProfileCommand->riderId);

        $rider = $this->userRepository->find($updateProfileCommand->riderId);
        $this->userRepository->updateProfile(
            $updateProfileCommand->riderId,
            $updateProfileCommand->name,
            $updateProfileCommand->email,
            ! $rider->email->equals($updateProfileCommand->email),
        );
    }
}
