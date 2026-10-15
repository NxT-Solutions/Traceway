<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\RiderId;
use App\Account\RouteService;
use App\Account\RouteServiceTokens;
use App\Routes\RouteId;

/**
 * A rider's connected route services, and which routes went to them.
 */
interface RouteServiceRepository
{
    /**
     * Stores the tokens of a new connection, or replaces those of an old one.
     */
    public function connect(RiderId $riderId, RouteService $routeService, RouteServiceTokens $routeServiceTokens): void;

    /**
     * The tokens of a connection, or null when the rider has not connected the service.
     */
    public function tokens(RiderId $riderId, RouteService $routeService): ?RouteServiceTokens;

    public function refreshed(RiderId $riderId, RouteService $routeService, RouteServiceTokens $routeServiceTokens): void;

    /**
     * The service refused the token. The rider sees "Reconnect" until they connect again.
     */
    public function needsReconnect(RiderId $riderId, RouteService $routeService): void;

    public function disconnect(RiderId $riderId, RouteService $routeService): void;

    /**
     * @return list<RouteService>
     */
    public function connected(RiderId $riderId): array;

    /**
     * The service's id for a route or leg sent before, so the next send replaces it.
     */
    public function sentId(RiderId $riderId, RouteId $routeId, int $leg, RouteService $routeService): ?string;

    public function recordSent(RiderId $riderId, RouteId $routeId, int $leg, RouteService $routeService, ?string $externalId, ?string $externalUrl): void;
}
