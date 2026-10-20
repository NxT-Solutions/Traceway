<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\RiderId;

/**
 * The AI tools a rider let in through OAuth.
 */
interface ConnectedAppRepository
{
    /**
     * Revoke every token the app holds for this rider. The app has to ask again.
     */
    public function disconnect(RiderId $riderId, string $clientId): void;

    /**
     * Revoke every token any app holds for this rider.
     */
    public function disconnectAll(RiderId $riderId): void;
}
