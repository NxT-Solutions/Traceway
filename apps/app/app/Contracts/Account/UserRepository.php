<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\Email;
use App\Account\Rider;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Account\Role;
use DateTimeImmutable;
use SensitiveParameter;

interface UserRepository
{
    public function create(RiderName $riderName, Email $email, #[SensitiveParameter] string $password): Rider;

    public function find(RiderId $riderId): Rider;

    public function updateProfile(RiderId $riderId, RiderName $riderName, Email $email, bool $clearEmailVerification): void;

    /**
     * Marks the rider as welcomed. Returns null when the rider was welcomed before.
     */
    public function markWelcomed(RiderId $riderId): ?Rider;

    public function changePassword(RiderId $riderId, #[SensitiveParameter] string $password): Rider;

    public function delete(RiderId $riderId): Rider;

    /**
     * Opens an account for someone else, with the email already confirmed and no welcome to come. They set their
     * own password from a link.
     */
    public function createConfirmed(RiderName $riderName, Email $email, #[SensitiveParameter] string $password): Rider;

    public function role(RiderId $riderId): Role;

    /**
     * Gives the rider this one role in place of any other.
     */
    public function assignRole(RiderId $riderId, Role $role): void;

    public function countWithRole(Role $role): int;

    public function confirmEmail(RiderId $riderId): void;

    /**
     * Turns two-factor sign-in off, for a rider who lost the phone and the recovery codes.
     */
    public function resetTwoFactor(RiderId $riderId): void;

    /**
     * Signs the rider out everywhere: every session ends and Remember me stops working.
     */
    public function endSessions(RiderId $riderId): void;

    /**
     * Notes that the rider was signed in and using Traceway at this time.
     */
    public function seen(RiderId $riderId, DateTimeImmutable $seenAt): void;
}
