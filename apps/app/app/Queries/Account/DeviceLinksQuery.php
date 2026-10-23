<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\DeviceProvider;
use App\Queries\RowReader;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The devices a rider has linked. A device the rider typed the code of, but that has not collected its token yet, is
 * listed as waiting until its code runs out.
 */
final class DeviceLinksQuery
{
    /**
     * @return list<array{id: string, label: string, provider: string, linked_at: string|null, last_used_at: string|null, waiting: bool}>
     */
    public function forRider(int $riderId): array
    {
        $rows = DB::table('device_links')
            ->where('user_id', $riderId)
            ->where(static fn (Builder $builder): Builder => $builder
                ->whereNotNull('token_hash')
                ->orWhere('pairing_expires_at', '>', now()))
            ->latest('linked_at')
            ->get(['id', 'provider', 'label', 'linked_at', 'last_used_at', 'token_hash']);

        $devices = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $devices[] = [
                'id' => $reader->string('id'),
                'label' => $reader->optionalString('label') ?? 'Device',
                'provider' => (DeviceProvider::tryFrom($reader->string('provider')) ?? DeviceProvider::Garmin)->label(),
                'linked_at' => $reader->iso('linked_at'),
                'last_used_at' => $reader->iso('last_used_at'),
                'waiting' => $reader->optionalString('token_hash') === null,
            ];
        }

        return $devices;
    }
}
