<?php

declare(strict_types=1);

namespace App\Auth\Connections\Providers;

use Override;
use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\Strava\Provider;

/**
 * Strava's sign-in, asking only to read the public profile. Strava requires a scope, which the package leaves
 * out, and its athlete may come without a name.
 */
final class StravaProvider extends Provider
{
    /**
     * @var array<mixed>
     */
    #[Override]
    protected $scopes = ['read'];

    /**
     * @param  array<mixed>  $user
     */
    #[Override]
    protected function mapUserToObject(array $user): User
    {
        $id = $user['id'] ?? null;
        $parts = array_filter([$user['firstname'] ?? null, $user['lastname'] ?? null], is_string(...));

        return (new User)->setRaw($user)->map([
            'id' => is_int($id) || is_string($id) ? (string) $id : null,
            'nickname' => null,
            'name' => implode(' ', $parts),
            'email' => null,
            'avatar' => null,
        ]);
    }
}
