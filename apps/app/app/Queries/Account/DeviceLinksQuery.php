<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Queries\RowReader;
use Illuminate\Support\Facades\DB;

/**
 * The devices a rider has linked. Pairings that still wait for their token are left out.
 */
final class DeviceLinksQuery
{
    /**
     * @return list<array{id: string, label: string, linked_at: string|null, last_used_at: string|null}>
     */
    public function forRider(int $riderId): array
    {
        $rows = DB::table('device_links')
            ->where('user_id', $riderId)
            ->whereNotNull('token_hash')
            ->latest('linked_at')
            ->get(['id', 'label', 'linked_at', 'last_used_at']);

        $devices = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $devices[] = [
                'id' => $reader->string('id'),
                'label' => $reader->optionalString('label') ?? 'Garmin',
                'linked_at' => $reader->iso('linked_at'),
                'last_used_at' => $reader->iso('last_used_at'),
            ];
        }

        return $devices;
    }
}
