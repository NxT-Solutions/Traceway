<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\Email;
use App\Account\Rider;
use App\Account\RiderId;
use App\Account\RiderName;
use SensitiveParameter;

interface UserRepository
{
    public function create(RiderName $riderName, Email $email, #[SensitiveParameter] string $password): Rider;

    public function find(RiderId $riderId): Rider;

    public function updateProfile(RiderId $riderId, RiderName $riderName, Email $email, bool $clearEmailVerification): void;

    public function changePassword(RiderId $riderId, #[SensitiveParameter] string $password): Rider;

    public function delete(RiderId $riderId): Rider;
}
