<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\AdminAction;
use App\Account\RiderId;
use App\Contracts\Account\AdminLog;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseAdminLog implements AdminLog
{
    public function record(?RiderId $actorId, AdminAction $adminAction, ?RiderId $riderId, array $details = []): void
    {
        DB::table('admin_actions')->insert([
            'actor_id' => $actorId?->value,
            'rider_id' => $riderId?->value,
            'action' => $adminAction->value,
            'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function forgetBefore(DateTimeImmutable $before): int
    {
        return DB::table('admin_actions')->where('created_at', '<', CarbonImmutable::instance($before))->delete();
    }
}
