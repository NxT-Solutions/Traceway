<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps;

use App\Integrations\RideWithGps\Requests\GetAccessTokenRequest;
use Saloon\Contracts\OAuthAuthenticator;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Ride with GPS. A route stored here reaches a Karoo, a Bryton, or a Wahoo once the rider sends it on with Ride with GPS's Send to Device.
 * Its tokens do not expire, and it has no refresh token.
 */
final class RideWithGpsConnector extends Connector
{
    use AcceptsJson;
    use AuthorizationCodeGrant;
    use HasTimeout;

    protected int $connectTimeout = 5;

    protected int $requestTimeout = 30;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://ridewithgps.com',
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Trades the code from the sign-in redirect for tokens, leaving the answer to the caller.
     * Saloon's getAccessToken() hides which failures it throws.
     *
     * @throws FatalRequestException when the service cannot be reached
     */
    public function tradeCode(string $code): Response
    {
        return $this->send($this->resolveAccessTokenRequest($code, $this->oauthConfig()));
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

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function clientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return $this->apiKey === null || $this->apiKey === '' ? [] : ['x-rwgps-api-key' => $this->apiKey];
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId($this->clientId)
            ->setClientSecret($this->clientSecret)
            ->setRedirectUri($this->redirectUri)
            ->setAuthorizeEndpoint('/oauth/authorize')
            ->setTokenEndpoint('/oauth/token.json')
            ->setUserEndpoint('/api/v1/users/current.json');
    }

    protected function resolveAccessTokenRequest(string $code, OAuthConfig $oauthConfig): GetAccessTokenRequest
    {
        return new GetAccessTokenRequest($code, $oauthConfig);
    }
}
