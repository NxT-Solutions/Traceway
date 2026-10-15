<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Account\RouteService;
use App\Account\RouteServiceTokens;
use App\Contracts\Account\RouteServiceRepository;
use App\Models\RouteServiceAccount;
use App\Routes\RouteId;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class EloquentRouteServiceRepository implements RouteServiceRepository
{
    public function connect(RiderId $riderId, RouteService $routeService, RouteServiceTokens $routeServiceTokens): void
    {
        $account = $this->account($riderId, $routeService) ?? new RouteServiceAccount([
            'user_id' => $riderId->value,
            'service' => $routeService->value,
        ]);

        $account->fill([
            'access_token' => $routeServiceTokens->accessToken,
            'refresh_token' => $routeServiceTokens->refreshToken,
            'expires_at' => $this->expiry($routeServiceTokens),
            'needs_reconnect' => false,
            'connected_at' => now(),
        ])->save();
    }

    public function tokens(RiderId $riderId, RouteService $routeService): ?RouteServiceTokens
    {
        $account = $this->account($riderId, $routeService);

        if (! $account instanceof RouteServiceAccount) {
            return null;
        }

        return new RouteServiceTokens(
            $account->access_token,
            $account->refresh_token,
            $account->expires_at?->getTimestamp(),
        );
    }

    public function refreshed(RiderId $riderId, RouteService $routeService, RouteServiceTokens $routeServiceTokens): void
    {
        $this->account($riderId, $routeService)?->fill([
            'access_token' => $routeServiceTokens->accessToken,
            'refresh_token' => $routeServiceTokens->refreshToken,
            'expires_at' => $this->expiry($routeServiceTokens),
            'needs_reconnect' => false,
        ])->save();
    }

    public function needsReconnect(RiderId $riderId, RouteService $routeService): void
    {
        $this->account($riderId, $routeService)?->fill(['needs_reconnect' => true])->save();
    }

    public function disconnect(RiderId $riderId, RouteService $routeService): void
    {
        RouteServiceAccount::query()
            ->where('user_id', $riderId->value)
            ->where('service', $routeService->value)
            ->delete();

        DB::table('sent_routes')
            ->where('user_id', $riderId->value)
            ->where('service', $routeService->value)
            ->delete();
    }

    /**
     * @return list<RouteService>
     */
    public function connected(RiderId $riderId): array
    {
        $services = [];

        foreach (RouteServiceAccount::query()->where('user_id', $riderId->value)->pluck('service') as $slug) {
            $service = is_string($slug) ? RouteService::tryFrom($slug) : null;

            if ($service instanceof RouteService) {
                $services[] = $service;
            }
        }

        return $services;
    }

    public function sentId(RiderId $riderId, RouteId $routeId, int $leg, RouteService $routeService): ?string
    {
        $id = DB::table('sent_routes')
            ->where('user_id', $riderId->value)
            ->where('saved_route_id', $routeId->value)
            ->where('leg', $leg)
            ->where('service', $routeService->value)
            ->value('external_id');

        return is_string($id) ? $id : null;
    }

    public function recordSent(RiderId $riderId, RouteId $routeId, int $leg, RouteService $routeService, ?string $externalId, ?string $externalUrl): void
    {
        DB::transaction(function () use ($riderId, $routeId, $leg, $routeService, $externalId, $externalUrl): void {
            DB::table('sent_routes')->updateOrInsert(
                ['saved_route_id' => $routeId->value, 'leg' => $leg, 'service' => $routeService->value],
                ['user_id' => $riderId->value, 'external_id' => $externalId, 'external_url' => $externalUrl, 'sent_at' => now()],
            );

            $this->account($riderId, $routeService)?->fill(['last_sent_at' => now()])->save();
        });
    }

    private function account(RiderId $riderId, RouteService $routeService): ?RouteServiceAccount
    {
        return RouteServiceAccount::query()
            ->where('user_id', $riderId->value)
            ->where('service', $routeService->value)
            ->first();
    }

    private function expiry(RouteServiceTokens $routeServiceTokens): ?CarbonInterface
    {
        return $routeServiceTokens->expiresAt === null ? null : Date::createFromTimestamp($routeServiceTokens->expiresAt);
    }
}
