<?php

declare(strict_types=1);

namespace App\Auth;

use App\Account\MissingRider;
use App\Account\RiderId;
use App\Contracts\Auth\Impersonator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Acting as a rider swaps who the session belongs to and keeps the Super Admin's id beside it. Nothing about the
 * rider's own sign-in changes: no password, no Remember me, no new session in their name.
 */
final readonly class SessionImpersonator implements Impersonator
{
    public function start(RiderId $actorId, RiderId $riderId): void
    {
        $rider = User::query()->find($riderId->value);

        if (! $rider instanceof User) {
            throw MissingRider::id($riderId->value);
        }

        // Set first, so the sign-in below is known as acting and is not counted as the rider's own.
        Session::put(Impersonation::SESSION_KEY, $actorId->value);
        Session::forget(['auth.password_confirmed_at', 'last_seen_noted_at']);
        Auth::guard('web')->login($rider);
    }

    public function stop(): ?array
    {
        $actorId = Session::get(Impersonation::SESSION_KEY);
        $riderId = Auth::guard('web')->id();

        if (! is_int($actorId)) {
            return null;
        }

        $actor = User::query()->find($actorId);
        Session::forget(Impersonation::SESSION_KEY);

        if (! $actor instanceof User) {
            Auth::guard('web')->logout();

            return null;
        }

        Session::forget(['auth.password_confirmed_at', 'last_seen_noted_at']);
        Auth::guard('web')->login($actor);

        return [
            'actor' => new RiderId($actorId),
            'rider' => new RiderId(is_int($riderId) ? $riderId : (int) $riderId),
        ];
    }
}
