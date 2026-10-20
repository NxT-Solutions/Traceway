<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Connections\GrantedAccess;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Access a service gave, such as Wahoo's, that remembers what Traceway asked of it.
 */
final class RecordingGrantedAccess implements GrantedAccess
{
    /** @var list<string> The access tokens Traceway refreshed, in turn. */
    public array $refreshed = [];

    /** @var list<string> The access tokens Traceway handed back, in turn. */
    public array $revoked = [];

    /** @var list<string> The access tokens a newer log in or connect replaced, in turn. */
    public array $superseded = [];

    /**
     * @var array<string, string> 'refused' or 'unavailable' for an access token makes refreshing it fail that way.
     */
    public array $failures = [];

    public function refresh(ServiceTokens $serviceTokens): ServiceTokens
    {
        $this->refreshed[] = $serviceTokens->accessToken;

        $failure = $this->failures[$serviceTokens->accessToken] ?? null;

        throw_if($failure === 'refused', ServiceRefused::class, 'The refresh token ran out.');

        throw_if($failure === 'unavailable', ServiceUnavailable::class, 'The service is down.');

        return new ServiceTokens('access-refreshed', 'refresh-refreshed');
    }

    public function revoke(ServiceTokens $serviceTokens): void
    {
        $this->revoked[] = $serviceTokens->accessToken;
    }

    public function supersede(ServiceTokens $serviceTokens): void
    {
        $this->superseded[] = $serviceTokens->accessToken;
    }
}
