<?php

declare(strict_types=1);

namespace App\Integrations\Wahoo;

use Saloon\Contracts\OAuthAuthenticator;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Wahoo's cloud API. A route stored here syncs to the Wahoo app and on to an ELEMNT or ACE. The rider's sign-in
 * runs through Socialite; this connector only refreshes and uses the keys it gave.
 */
final class WahooConnector extends Connector
{
    use AcceptsJson;
    use AuthorizationCodeGrant;
    use HasTimeout;

    protected int $connectTimeout = 5;

    protected int $requestTimeout = 20;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl = 'https://api.wahooligan.com',
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @throws FatalRequestException when the service cannot be reached
     */
    public function tradeRefreshToken(string $refreshToken): Response
    {
        return $this->send($this->resolveRefreshTokenRequest($this->oauthConfig(), $refreshToken));
    }

    public function authenticator(Response $response, ?string $refreshToken = null): OAuthAuthenticator
    {
        return $this->createOAuthAuthenticatorFromResponse($response, $refreshToken);
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId($this->clientId)
            ->setClientSecret($this->clientSecret)
            ->setTokenEndpoint('/oauth/token');
    }
}
