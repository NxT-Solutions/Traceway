<?php

declare(strict_types=1);

namespace App\Integrations\GarminConnect;

use Saloon\Contracts\OAuthAuthenticator;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Garmin's OAuth 2.0 server. The rider's sign-in runs through Socialite; this connector only refreshes the keys,
 * which last a day, so Traceway can still withdraw its access later.
 */
final class GarminConnectTokenConnector extends Connector
{
    use AcceptsJson;
    use AuthorizationCodeGrant;
    use HasTimeout;

    protected int $connectTimeout = 5;

    protected int $requestTimeout = 20;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl = 'https://connectapi.garmin.com',
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
            ->setTokenEndpoint('/di-oauth2-service/oauth/token');
    }
}
