<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Account\MissingRider;
use App\Account\RiderId;
use App\Contracts\Account\EmailConfirmation;
use App\Models\User;

final readonly class RiderEmailConfirmation implements EmailConfirmation
{
    public function request(RiderId $riderId): void
    {
        $user = User::query()->find($riderId->value);

        if (! $user instanceof User) {
            throw MissingRider::id($riderId->value);
        }

        $user->sendEmailVerificationNotification();
    }
}
