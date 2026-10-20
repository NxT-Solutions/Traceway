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

    /**
     * Opens an account with no password, for a rider who came from a connected service. With a confirmed email
     * there is no confirmation link to wait for; the welcome is still to come.
     */
    public function createWithoutPassword(RiderName $riderName, Email $email, bool $emailConfirmed): Rider;

    public function find(RiderId $riderId): Rider;

    public function findByEmail(Email $email): ?Rider;

    /**
     * False for a rider who signed up with a connected service and never set a password.
     */
    public function hasPassword(RiderId $riderId): bool;

    public function passkeyCount(RiderId $riderId): int;

    public function deletePasskeys(RiderId $riderId): void;

    /**
     * Changes the name and, as an admin does, the email at once. A new email drops any change the rider was
     * waiting to confirm.
     */
    public function updateProfile(RiderId $riderId, RiderName $riderName, Email $email, bool $clearEmailVerification): void;

    /**
     * Keeps a new email the rider asked for until they open the link sent to it, in place of any earlier one.
     */
    public function requestEmailChange(RiderId $riderId, Email $email): void;

    public function pendingEmail(RiderId $riderId): ?Email;

    /**
     * Swaps the waiting email in, confirmed, when it is still the one waiting. Returns the email it replaced, or
     * null when that email no longer waits.
     */
    public function confirmEmailChange(RiderId $riderId, Email $email): ?Email;

    public function cancelEmailChange(RiderId $riderId): void;

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

    /**
     * Marks the email confirmed, as an admin does. Like an account opened for someone else, it counts as confirmed
     * once, so a later password reset never takes the rider's ways in away, and no welcome is to come.
     */
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
