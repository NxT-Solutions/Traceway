<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Account\LinkOutcome;
use App\Account\RiderId;
use App\Routes\RouteId;
use DateTimeImmutable;

/**
 * A rider's connected accounts at other services, the keys some of them keep, and the routes sent through them.
 */
interface ConnectionRepository
{
    /**
     * The rider this account at the service belongs to, or null when nobody connected it.
     */
    public function riderFor(ConnectionProvider $connectionProvider, string $providerUserId): ?RiderId;

    /**
     * The service's number for the account the rider connected, or null when they have no connection there, or
     * an old one that does not have it yet.
     */
    public function identityOf(RiderId $riderId, ConnectionProvider $connectionProvider): ?string;

    /**
     * Connects the account, or brings an existing connection up to date. Keys are replaced only when the identity
     * carries new ones. "Connected since" stays when it is the same account, or an old connection without a
     * number gets one.
     */
    public function link(RiderId $riderId, ConnectedIdentity $connectedIdentity): LinkOutcome;

    /**
     * The riders whose connection to the service has no number from it yet, and keys the service has not turned
     * down. Each could still be any account at the service, and the only ones worth asking about.
     *
     * @return list<RiderId>
     */
    public function unidentifiedWithLiveKeys(ConnectionProvider $connectionProvider): array;

    /**
     * Gives a connection without a number the service's number for the account, and the label it shows. A
     * connection that has a number already keeps it.
     */
    public function identify(RiderId $riderId, ConnectionProvider $connectionProvider, string $providerUserId, ?string $accountLabel): void;

    /**
     * The riders whose connection to the service keeps keys.
     *
     * @return list<RiderId>
     */
    public function withKeys(ConnectionProvider $connectionProvider): array;

    public function signedIn(RiderId $riderId, ConnectionProvider $connectionProvider, DateTimeImmutable $signedInAt): void;

    /**
     * Forgets the connection and the routes sent through it.
     */
    public function unlink(RiderId $riderId, ConnectionProvider $connectionProvider): void;

    /**
     * @return list<ConnectionProvider>
     */
    public function connected(RiderId $riderId): array;

    /**
     * The keys of a connection, or null when it keeps none.
     */
    public function tokens(RiderId $riderId, ConnectionProvider $connectionProvider): ?ConnectionTokens;

    public function refreshed(RiderId $riderId, ConnectionProvider $connectionProvider, ConnectionTokens $connectionTokens): void;

    /**
     * The service refused the keys. The rider sees "Connect again" until they do.
     */
    public function needsReconnect(RiderId $riderId, ConnectionProvider $connectionProvider): void;

    /**
     * The service turned the keys down for good, so they can do nothing any more. Forgets them, and the rider
     * sees "Connect again" until they do.
     */
    public function forgetKeys(RiderId $riderId, ConnectionProvider $connectionProvider): void;

    /**
     * The service's id for a route or leg sent before, so the next send replaces it.
     */
    public function sentId(RiderId $riderId, RouteId $routeId, int $leg, ConnectionProvider $connectionProvider): ?string;

    public function recordSent(RiderId $riderId, RouteId $routeId, int $leg, ConnectionProvider $connectionProvider, ?string $externalId, ?string $externalUrl): void;
}
