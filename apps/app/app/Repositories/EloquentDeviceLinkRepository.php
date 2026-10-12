<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\DeviceLabel;
use App\Account\DeviceLinkId;
use App\Account\DeviceProvider;
use App\Account\PairingCode;
use App\Account\PairingState;
use App\Account\RiderId;
use App\Contracts\Account\DeviceLinkRepository;
use App\Models\DeviceLink;
use Illuminate\Support\Facades\DB;

final readonly class EloquentDeviceLinkRepository implements DeviceLinkRepository
{
    public function open(DeviceProvider $deviceProvider, PairingCode $pairingCode, string $secretHash, int $seconds): bool
    {
        return DB::transaction(function () use ($deviceProvider, $pairingCode, $secretHash, $seconds): bool {
            DB::table('device_links')
                ->whereNull('token_hash')
                ->where('pairing_expires_at', '<', now())
                ->delete();

            if (DB::table('device_links')->where('pairing_code', $pairingCode->value)->exists()) {
                return false;
            }

            DeviceLink::query()->create([
                'provider' => $deviceProvider->value,
                'pairing_code' => $pairingCode->value,
                'pairing_secret_hash' => $secretHash,
                'pairing_expires_at' => now()->addSeconds($seconds),
            ]);

            return true;
        });
    }

    public function link(PairingCode $pairingCode, RiderId $riderId, DeviceLabel $deviceLabel): bool
    {
        return DB::table('device_links')
            ->where('pairing_code', $pairingCode->value)
            ->whereNull('user_id')
            ->whereNull('token_hash')
            ->where('pairing_expires_at', '>', now())
            ->update([
                'user_id' => $riderId->value,
                'label' => $deviceLabel->value,
                'linked_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    public function claim(string $secretHash, string $tokenHash): PairingState
    {
        return DB::transaction(function () use ($secretHash, $tokenHash): PairingState {
            $row = DB::table('device_links')
                ->where('pairing_secret_hash', $secretHash)
                ->lockForUpdate()
                ->first(['id', 'user_id', 'pairing_expires_at']);

            if ($row === null) {
                return PairingState::Gone;
            }

            $expires = $row->pairing_expires_at ?? null;

            if (! is_string($expires) || now()->greaterThanOrEqualTo($expires)) {
                DB::table('device_links')->where('id', $row->id)->delete();

                return PairingState::Gone;
            }

            if (($row->user_id ?? null) === null) {
                return PairingState::Waiting;
            }

            DB::table('device_links')->where('id', $row->id)->update([
                'token_hash' => $tokenHash,
                'pairing_code' => null,
                'pairing_secret_hash' => null,
                'pairing_expires_at' => null,
                'updated_at' => now(),
            ]);

            return PairingState::Linked;
        });
    }

    public function revoke(DeviceLinkId $deviceLinkId, RiderId $riderId): bool
    {
        return DB::table('device_links')
            ->where('id', $deviceLinkId->value)
            ->where('user_id', $riderId->value)
            ->delete() === 1;
    }

    public function riderForToken(string $tokenHash): ?RiderId
    {
        $row = DB::table('device_links')
            ->where('token_hash', $tokenHash)
            ->first(['id', 'user_id', 'last_used_at']);

        $owner = $row->user_id ?? null;

        if ($row === null || ! is_numeric($owner)) {
            return null;
        }

        $lastUsed = $row->last_used_at ?? null;

        if (! is_string($lastUsed) || now()->subMinutes(5)->greaterThan($lastUsed)) {
            DB::table('device_links')->where('id', $row->id)->update(['last_used_at' => now()]);
        }

        return new RiderId((int) $owner);
    }
}
