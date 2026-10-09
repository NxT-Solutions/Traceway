<?php

declare(strict_types=1);

namespace App\Auth;

use App\Account\RiderId;
use App\Contracts\Auth\CurrentRider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final readonly class SessionRider implements CurrentRider
{
    public function assertSame(RiderId $riderId): void
    {
        $id = $this->id();

        throw_if($id === null || $id !== $riderId->value, AuthorizationException::class, 'This account belongs to someone else.');
    }

    public function assertSameOrGuest(RiderId $riderId): void
    {
        $id = $this->id();

        throw_if($id !== null && $id !== $riderId->value, AuthorizationException::class, 'This account belongs to someone else.');
    }

    private function id(): ?int
    {
        $actor = Auth::id();

        if (is_int($actor)) {
            return $actor;
        }

        if (is_string($actor) && is_numeric($actor)) {
            return (int) $actor;
        }

        return null;
    }
}
