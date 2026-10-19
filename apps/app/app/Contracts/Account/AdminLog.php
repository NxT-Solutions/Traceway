<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\AdminAction;
use App\Account\RiderId;
use DateTimeImmutable;

/**
 * What was done to whose account, by whom, and when. Nothing else about the rider is kept here.
 */
interface AdminLog
{
    /**
     * @param  RiderId|null  $actorId  null when the server did it, such as a command or the address list
     * @param  RiderId|null  $riderId  null when the account is gone
     * @param  array<string, string>  $details  such as the role before and after
     */
    public function record(?RiderId $actorId, AdminAction $adminAction, ?RiderId $riderId, array $details = []): void;

    /**
     * Forgets the entries older than this moment.
     */
    public function forgetBefore(DateTimeImmutable $before): int;
}
