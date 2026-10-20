<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\DeviceLabel;
use App\Account\DeviceLinkId;
use App\Account\DeviceProvider;
use App\Account\PairingCode;
use App\Account\PairingState;
use App\Account\RiderId;

/**
 * Secrets and tokens arrive here already hashed. The plain values live only on the device.
 */
interface DeviceLinkRepository
{
    /**
     * Opens a pairing that waits for a rider. False when the code is already waiting.
     */
    public function open(DeviceProvider $deviceProvider, PairingCode $pairingCode, string $secretHash, int $seconds): bool;

    /**
     * Gives a waiting pairing to the rider. False when the code is unknown, used, or ran out.
     */
    public function link(PairingCode $pairingCode, RiderId $riderId, DeviceLabel $deviceLabel): bool;

    /**
     * Stores the token once the rider has linked the pairing, and closes the pairing.
     */
    public function claim(string $secretHash, string $tokenHash): PairingState;

    public function revoke(DeviceLinkId $deviceLinkId, RiderId $riderId): bool;

    /**
     * Unlinks every device of the rider. Their tokens stop working at once.
     */
    public function revokeAll(RiderId $riderId): void;

    /**
     * The rider behind a device token. Marks the link as used.
     */
    public function riderForToken(string $tokenHash): ?RiderId;
}
