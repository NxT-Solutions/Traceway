<?php

declare(strict_types=1);

namespace App\Auth\Connections\Providers;

use GuzzleHttp\RequestOptions;
use Override;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;
use UnexpectedValueException;

/**
 * Wahoo's cloud API sign-in. The rider allows Traceway to read who they are and to write routes, so one consent
 * logs them in and lets Send to device put routes on their ELEMNT or ACE. Wahoo grants only the scopes enabled on
 * the developer app, so the email is asked for only once the app has it (request_email), or the whole sign-in
 * would be refused.
 */
final class WahooProvider extends AbstractProvider
{
    public const string IDENTIFIER = 'WAHOO';

    /**
     * @var array<mixed>
     */
    #[Override]
    protected $scopes = ['user_read', 'routes_read', 'routes_write'];

    #[Override]
    protected $scopeSeparator = ' ';

    /**
     * @return list<string>
     */
    #[Override]
    public static function additionalConfigKeys(): array
    {
        return ['url', 'request_email'];
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function getScopes(): array
    {
        $scopes = parent::getScopes();

        return filter_var($this->getConfig('request_email', default: false), FILTER_VALIDATE_BOOLEAN) ? [...$scopes, 'email'] : $scopes;
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
        return $this->base().'/oauth/token';
    }

    /**
     * @param  string  $token
     * @return array<mixed>
     */
    #[Override]
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->base().'/v1/user', [
            RequestOptions::HEADERS => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token],
        ]);

        $user = json_decode((string) $response->getBody(), associative: true);

        throw_unless(is_array($user), UnexpectedValueException::class, 'Wahoo sent no account.');

        return $user;
    }

    /**
     * @param  array<mixed>  $user
     */
    #[Override]
    protected function mapUserToObject(array $user): User
    {
        $first = is_string($user['first'] ?? null) ? $user['first'] : '';
        $last = is_string($user['last'] ?? null) ? $user['last'] : '';
        $id = $user['id'] ?? null;

        return (new User)->setRaw($user)->map([
            'id' => is_int($id) || is_string($id) ? (string) $id : null,
            'nickname' => null,
            'name' => trim($first.' '.$last),
            'email' => is_string($user['email'] ?? null) ? $user['email'] : null,
            'avatar' => null,
        ]);
    }

    private function base(): string
    {
        $url = $this->getConfig('url', 'https://api.wahooligan.com');

        return rtrim(is_string($url) && $url !== '' ? $url : 'https://api.wahooligan.com', '/');
    }
}
