<?php

declare(strict_types=1);

namespace App\Auth\Connections\Providers;

use GuzzleHttp\RequestOptions;
use Override;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;
use UnexpectedValueException;

/**
 * Ride with GPS's sign-in. It has no scopes: the key it gives lets Traceway read who the rider is and put routes
 * in their library. It reads the token request as JSON and its keys never expire.
 */
final class RideWithGpsProvider extends AbstractProvider
{
    public const string IDENTIFIER = 'RIDE_WITH_GPS';

    #[Override]
    protected $scopeSeparator = ' ';

    /**
     * @return list<string>
     */
    #[Override]
    public static function additionalConfigKeys(): array
    {
        return ['url', 'api_key'];
    }

    /**
     * @param  string  $state
     */
    #[Override]
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->base().'/oauth/authorize', $state);
    }

    #[Override]
    protected function getTokenUrl(): string
    {
        return $this->base().'/oauth/token.json';
    }

    /**
     * Ride with GPS asks for no scopes, so none are sent.
     *
     * @param  string|null  $state
     * @return array<mixed>
     */
    #[Override]
    protected function getCodeFields($state = null): array
    {
        $fields = parent::getCodeFields($state);

        if (($fields['scope'] ?? '') === '') {
            unset($fields['scope']);
        }

        return $fields;
    }

    /**
     * @param  string  $code
     * @return array<mixed>
     */
    #[Override]
    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => ['Accept' => 'application/json'],
            RequestOptions::JSON => $this->getTokenFields($code),
        ]);

        $body = json_decode((string) $response->getBody(), associative: true);

        return is_array($body) ? $body : [];
    }

    /**
     * @param  string  $token
     * @return array<mixed>
     */
    #[Override]
    protected function getUserByToken($token): array
    {
        $apiKey = $this->getConfig('api_key');
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];

        if (is_string($apiKey) && $apiKey !== '') {
            $headers['x-rwgps-api-key'] = $apiKey;
        }

        $response = $this->getHttpClient()->get($this->base().'/api/v1/users/current.json', [
            RequestOptions::HEADERS => $headers,
        ]);

        $body = json_decode((string) $response->getBody(), associative: true);
        $user = is_array($body) ? ($body['user'] ?? null) : null;

        throw_unless(is_array($user), UnexpectedValueException::class, 'Ride with GPS sent no account.');

        return $user;
    }

    /**
     * @param  array<mixed>  $user
     */
    #[Override]
    protected function mapUserToObject(array $user): User
    {
        $id = $user['id'] ?? null;
        $displayName = is_string($user['display_name'] ?? null) ? $user['display_name'] : null;
        $parts = array_filter([$user['first_name'] ?? null, $user['last_name'] ?? null], is_string(...));
        $name = is_string($user['name'] ?? null) && $user['name'] !== '' ? $user['name'] : ($displayName ?? implode(' ', $parts));

        return (new User)->setRaw($user)->map([
            'id' => is_int($id) || is_string($id) ? (string) $id : null,
            'nickname' => $displayName,
            'name' => $name,
            'email' => is_string($user['email'] ?? null) ? $user['email'] : null,
            'avatar' => null,
        ]);
    }

    private function base(): string
    {
        $url = $this->getConfig('url', 'https://ridewithgps.com');

        return rtrim(is_string($url) && $url !== '' ? $url : 'https://ridewithgps.com', '/');
    }
}
