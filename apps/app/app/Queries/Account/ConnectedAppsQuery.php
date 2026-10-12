<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Queries\RowReader;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The AI tools that can still plan for a rider: an app with a live token.
 */
final class ConnectedAppsQuery
{
    /**
     * @return list<ConnectedApp>
     */
    public function forRider(int $riderId): array
    {
        $rows = DB::table('oauth_access_tokens')
            ->join('oauth_clients', 'oauth_clients.id', '=', 'oauth_access_tokens.client_id')
            ->where('oauth_access_tokens.user_id', $riderId)
            ->where('oauth_access_tokens.revoked', operator: false)
            ->where(fn (Builder $builder) => $builder
                ->whereNull('oauth_access_tokens.expires_at')
                ->orWhere('oauth_access_tokens.expires_at', '>', now()))
            ->groupBy('oauth_clients.id', 'oauth_clients.name')
            ->orderBy('oauth_clients.name')
            ->get([
                'oauth_clients.id',
                'oauth_clients.name',
                DB::raw('min(oauth_access_tokens.created_at) as connected_at'),
            ]);

        return array_values($rows->map(function (object $row): ConnectedApp {
            $rowReader = new RowReader($row);

            return new ConnectedApp(
                $rowReader->string('id'),
                $rowReader->string('name'),
                $rowReader->iso('connected_at'),
            );
        })->all());
    }
}
