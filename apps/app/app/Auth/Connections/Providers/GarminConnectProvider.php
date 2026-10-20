<?php

declare(strict_types=1);

namespace App\Auth\Connections\Providers;

use GuzzleHttp\RequestOptions;
use Override;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;
use UnexpectedValueException;

/**
 * Garmin Connect's OAuth 2.0 sign-in for its developer program, with PKCE as Garmin requires. Garmin says only
 * the rider's user number, with no name or email. The SocialiteProviders package still speaks OAuth 1.0a, which
 * Garmin retires, and returns no user number, so Traceway has its own.
 */
final class GarminConnectProvider extends AbstractProvider
{
    public const string IDENTIFIER = 'GARMIN_CONNECT';

    #[Override]
    protected $usesPKCE = true;

    #[Override]
    protected $scopeSeparator = ' ';

    /**
     * @return list<string>
     */
    #[Override]
    public static function additionalConfigKeys(): array
    {
        return ['authorize_url', 'token_url', 'api_url'];
    }

    /**
     * @param  string  $state
     */
    #[Override]
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->url('authorize_url', 'https://connect.garmin.com/oauth2Confirm'), $state);
    }

    #[Override]
    protected function getTokenUrl(): string
    {
        return $this->url('token_url', 'https://connectapi.garmin.com/di-oauth2-service/oauth/token');
    }

    /**
     * The scopes are fixed on Garmin's side for each app, so none are sent.
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
     * @param  string  $token
     * @return array<mixed>
     */
    #[Override]
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->url('api_url', 'https://apis.garmin.com').'/wellness-api/rest/user/id', [
            RequestOptions::HEADERS => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token],
        ]);

        $user = json_decode((string) $response->getBody(), associative: true);

        throw_unless(is_array($user), UnexpectedValueException::class, 'Garmin sent no user number.');

        return $user;
    }

    /**
     * @param  array<mixed>  $user
     */
    #[Override]
    protected function mapUserToObject(array $user): User
    {
        $id = $user['userId'] ?? null;

        return (new User)->setRaw($user)->map([
            'id' => is_int($id) || is_string($id) ? (string) $id : null,
            'nickname' => null,
            'name' => null,
            'email' => null,
            'avatar' => null,
        ]);
    }

    private function url(string $key, string $default): string
    {
        $url = $this->getConfig($key, $default);

        return rtrim(is_string($url) && $url !== '' ? $url : $default, '/');
    }
}
