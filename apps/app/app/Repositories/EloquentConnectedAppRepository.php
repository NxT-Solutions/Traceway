<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Contracts\Account\ConnectedAppRepository;
use Illuminate\Support\Facades\DB;

final class EloquentConnectedAppRepository implements ConnectedAppRepository
{
    public function disconnect(RiderId $riderId, string $clientId): void
    {
        DB::transaction(function () use ($riderId, $clientId): void {
            $builder = DB::table('oauth_access_tokens')
                ->where('user_id', $riderId->value)
                ->where('client_id', $clientId);

            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', (clone $builder)->select('id'))
                ->update(['revoked' => true]);

            $builder->update(['revoked' => true]);

            DB::table('oauth_auth_codes')
                ->where('user_id', $riderId->value)
                ->where('client_id', $clientId)
                ->update(['revoked' => true]);
        });
    }

    public function disconnectAll(RiderId $riderId): void
    {
        DB::transaction(function () use ($riderId): void {
            $builder = DB::table('oauth_access_tokens')->where('user_id', $riderId->value);

            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', (clone $builder)->select('id'))
                ->update(['revoked' => true]);

            $builder->update(['revoked' => true]);

            DB::table('oauth_auth_codes')->where('user_id', $riderId->value)->update(['revoked' => true]);
        });
    }
}
