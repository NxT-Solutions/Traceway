<?php

declare(strict_types=1);

namespace App\Domain\Connections;

use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * The services whose keys Traceway can hand back, by slug. A service without credentials is left out, and so is
 * one that gives Traceway no keys to keep.
 */
final readonly class GrantedAccesses
{
    /**
     * @param  array<string, GrantedAccess>  $accesses
     */
    public function __construct(private array $accesses) {}

    public function has(string $slug): bool
    {
        return isset($this->accesses[$slug]);
    }

    /**
     * Asks the service to withdraw Traceway's access. A token that ran out is refreshed first, or the service
     * would refuse the request and keep the access. A service that is not set up has nothing to ask.
     *
     * @throws ServiceRefused when the service no longer accepts the token
     * @throws ServiceUnavailable when the service cannot be reached
     */
    public function withdraw(string $slug, ServiceTokens $serviceTokens, int $now): void
    {
        $grantedAccess = $this->accesses[$slug] ?? null;

        if (! $grantedAccess instanceof GrantedAccess) {
            return;
        }

        if ($serviceTokens->expiresBy($now)) {
            $serviceTokens = $grantedAccess->refresh($serviceTokens);
        }

        $grantedAccess->revoke($serviceTokens);
    }

    /**
     * Lets go of the keys a new log in or connect replaced. Nothing happens when the service handed back the same
     * key, or is not set up.
     *
     * @throws ServiceRefused when the service no longer accepts the old key
     * @throws ServiceUnavailable when the service cannot be reached
     */
    public function supersede(string $slug, ServiceTokens $old, ServiceTokens $new): void
    {
        $grantedAccess = $this->accesses[$slug] ?? null;

        if (! $grantedAccess instanceof GrantedAccess || $old->accessToken === $new->accessToken) {
            return;
        }

        $grantedAccess->supersede($old);
    }
}
