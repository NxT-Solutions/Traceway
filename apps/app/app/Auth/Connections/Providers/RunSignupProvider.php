<?php

declare(strict_types=1);

namespace App\Auth\Connections\Providers;

use Override;
use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\RunSignup\Provider;
use UnexpectedValueException;

/**
 * RunSignup's sign-in, asking only to read. RunSignup answers an error with HTTP 200 and an error body, which the
 * package reads as an account.
 */
final class RunSignupProvider extends Provider
{
    /**
     * @var array<mixed>
     */
    #[Override]
    protected $scopes = ['rsu_api_read'];

    #[Override]
    protected $scopeSeparator = ' ';

    #[Override]
    protected $usesPKCE = true;

    /**
     * @param  array<mixed>  $user
     */
    #[Override]
    protected function mapUserToObject(array $user): User
    {
        $account = $user['user'] ?? null;

        throw_if(isset($user['error']) || ! is_array($account), UnexpectedValueException::class, 'RunSignup sent no account.');

        $id = $account['user_id'] ?? null;
        $parts = array_filter([$account['first_name'] ?? null, $account['last_name'] ?? null], is_string(...));

        return (new User)->setRaw($account)->map([
            'id' => is_int($id) || is_string($id) ? (string) $id : null,
            'nickname' => null,
            'name' => implode(' ', $parts),
            'email' => is_string($account['email'] ?? null) ? $account['email'] : null,
            'avatar' => null,
        ]);
    }
}
