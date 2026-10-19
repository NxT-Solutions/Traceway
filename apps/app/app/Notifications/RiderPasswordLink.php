<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Account\MissingRider;
use App\Account\RiderId;
use App\Contracts\Account\PasswordLink;
use App\Models\User;
use Illuminate\Support\Facades\Password;

final readonly class RiderPasswordLink implements PasswordLink
{
    public function send(RiderId $riderId): void
    {
        $user = User::query()->find($riderId->value);

        if (! $user instanceof User) {
            throw MissingRider::id($riderId->value);
        }

        $user->sendPasswordResetNotification(Password::createToken($user));
    }
}
