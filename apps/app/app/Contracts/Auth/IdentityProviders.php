<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\Account\ConnectedIdentity;
use App\Account\ConnectionFailed;
use App\Account\ConnectionProvider;

/**
 * The services a rider can log in with or connect, through each service's own sign-in page. A service without
 * credentials stays hidden.
 */
interface IdentityProviders
{
    /**
     * The services set up on this Traceway, in the order the sign-in page offers them before riders use any.
     *
     * @return list<ConnectionProvider>
     */
    public function enabled(): array;

    public function isEnabled(ConnectionProvider $connectionProvider): bool;

    /**
     * Where to send the rider to allow Traceway. The state that guards the answer is kept in their session.
     */
    public function redirectUrl(ConnectionProvider $connectionProvider): string;

    /**
     * Who the rider is at the service, from the answer the service sent back to this request.
     *
     * @throws ConnectionFailed when the rider cancelled, the answer is not one Traceway asked for, or the
     *                          service could not be reached or did not say who the rider is
     */
    public function identity(ConnectionProvider $connectionProvider): ConnectedIdentity;
}
