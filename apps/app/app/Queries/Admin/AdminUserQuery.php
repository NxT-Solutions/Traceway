<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Account\RouteService;
use App\Queries\Account\RiderRoles;
use App\Queries\RowReader;
use Illuminate\Support\Facades\DB;

/**
 * One rider in the admin area: the account, what is linked to it, and where it is signed in. Secrets, tokens, and
 * the addresses of the sessions are never selected.
 *
 * @phpstan-type Watch array{label: string|null, provider: string, linked_at: string|null, last_used_at: string|null}
 * @phpstan-type App array{name: string, connected_at: string|null}
 * @phpstan-type Service array{service: string, connected_at: string|null, last_sent_at: string|null}
 * @phpstan-type Session array{browser: string, last_active_at: string}
 * @phpstan-type User array{id: int, name: string, email: string, role: string, email_verified_at: string|null, two_factor: bool, passkeys: int, created_at: string|null, updated_at: string|null, last_seen_at: string|null, routes: int, watches: list<Watch>, apps: list<App>, services: list<Service>, sessions: list<Session>}
 */
final readonly class AdminUserQuery
{
    public function __construct(private RiderRoles $riderRoles) {}

    /**
     * @return User|null
     */
    public function find(int $riderId): ?array
    {
        $row = DB::table('users')->where('id', $riderId)->first(['id', 'name', 'email', 'email_verified_at', 'two_factor_confirmed_at', 'created_at', 'updated_at', 'last_seen_at']);

        if ($row === null) {
            return null;
        }

        $rowReader = new RowReader($row);

        return [
            'id' => $rowReader->int('id'),
            'name' => $rowReader->string('name'),
            'email' => $rowReader->string('email'),
            'role' => $this->riderRoles->of($riderId)->value,
            'email_verified_at' => $rowReader->iso('email_verified_at'),
            'two_factor' => $rowReader->optionalString('two_factor_confirmed_at') !== null,
            'passkeys' => DB::table('passkeys')->where('user_id', $riderId)->count(),
            'created_at' => $rowReader->iso('created_at'),
            'updated_at' => $rowReader->iso('updated_at'),
            'last_seen_at' => $rowReader->iso('last_seen_at'),
            'routes' => DB::table('saved_routes')->where('user_id', $riderId)->count(),
            'watches' => $this->watches($riderId),
            'apps' => $this->apps($riderId),
            'services' => $this->services($riderId),
            'sessions' => $this->sessions($riderId),
        ];
    }

    /**
     * @return list<Watch>
     */
    private function watches(int $riderId): array
    {
        return array_values(DB::table('device_links')
            ->where('user_id', $riderId)
            ->whereNotNull('linked_at')
            ->latest('linked_at')
            ->get(['label', 'provider', 'linked_at', 'last_used_at'])
            ->map(function (object $row): array {
                $rowReader = new RowReader($row);

                return [
                    'label' => $rowReader->optionalString('label'),
                    'provider' => $rowReader->string('provider'),
                    'linked_at' => $rowReader->iso('linked_at'),
                    'last_used_at' => $rowReader->iso('last_used_at'),
                ];
            })
            ->all());
    }

    /**
     * @return list<App>
     */
    private function apps(int $riderId): array
    {
        return array_values(DB::table('oauth_access_tokens')
            ->join('oauth_clients', 'oauth_clients.id', '=', 'oauth_access_tokens.client_id')
            ->where('oauth_access_tokens.user_id', $riderId)
            ->where('oauth_access_tokens.revoked', operator: false)
            ->groupBy('oauth_clients.name')
            ->selectRaw('oauth_clients.name as name, min(oauth_access_tokens.created_at) as connected_at')
            ->orderBy('name')
            ->get()
            ->map(function (object $row): array {
                $rowReader = new RowReader($row);

                return ['name' => $rowReader->optionalString('name') ?? 'Unnamed app', 'connected_at' => $rowReader->iso('connected_at')];
            })
            ->all());
    }

    /**
     * @return list<Service>
     */
    private function services(int $riderId): array
    {
        return array_values(DB::table('route_service_accounts')
            ->where('user_id', $riderId)
            ->orderBy('service')
            ->get(['service', 'connected_at', 'last_sent_at'])
            ->map(function (object $row): array {
                $rowReader = new RowReader($row);
                $service = RouteService::tryFrom($rowReader->string('service'));

                return [
                    'service' => $service instanceof RouteService ? $service->label() : $rowReader->string('service'),
                    'connected_at' => $rowReader->iso('connected_at'),
                    'last_sent_at' => $rowReader->iso('last_sent_at'),
                ];
            })
            ->all());
    }

    /**
     * @return list<Session>
     */
    private function sessions(int $riderId): array
    {
        return array_values(DB::table('sessions')
            ->where('user_id', $riderId)
            ->orderByDesc('last_activity')
            ->get(['user_agent', 'last_activity'])
            ->map(function (object $row): array {
                $rowReader = new RowReader($row);

                return [
                    'browser' => $this->browser($rowReader->optionalString('user_agent')),
                    'last_active_at' => now()->setTimestamp($rowReader->int('last_activity'))->toIso8601String(),
                ];
            })
            ->all());
    }

    /**
     * A short name for the browser and system, so the page does not show the whole user agent.
     */
    private function browser(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'Unknown browser';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Another browser',
        };
        $system = match (true) {
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $system === null ? $browser : $browser.' on '.$system;
    }
}
