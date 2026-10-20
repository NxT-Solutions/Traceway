<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Account\ConnectionProvider;
use App\Account\RiderId;
use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\IdentityProviders;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;

/**
 * Removes a passkey, but not the last way a rider can log in. A rider without a password keeps their last
 * passkey until they set a password or connect a service that is set up here, the same rule Connections uses
 * before a service is disconnected.
 */
final class DeletePasskeyKeepingAWayIn extends DeletePasskey
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ConnectionRepository $connectionRepository,
        private readonly IdentityProviders $identityProviders,
    ) {}

    public function __invoke(Authenticatable $user, Passkey $passkey): void
    {
        $id = $user->getAuthIdentifier();

        throw_if(is_int($id) && ! $this->keepsAWayIn(new RiderId($id)), ValidationException::withMessages([
            'passkey' => 'Add a password or connect a service first, so you can still log in.',
        ]));

        parent::__invoke($user, $passkey);
    }

    /**
     * True when the rider can still log in without one passkey: with a password, another passkey, or a
     * connection to a service that is set up here.
     */
    private function keepsAWayIn(RiderId $riderId): bool
    {
        if ($this->userRepository->hasPassword($riderId) || $this->userRepository->passkeyCount($riderId) > 1) {
            return true;
        }

        return array_any($this->connectionRepository->connected($riderId), fn (ConnectionProvider $connectionProvider): bool => $this->identityProviders->isEnabled($connectionProvider));
    }
}
