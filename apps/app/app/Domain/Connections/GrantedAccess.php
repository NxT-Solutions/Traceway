<?php

declare(strict_types=1);

namespace App\Domain\Connections;

use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * The access a service gave Traceway for one rider, through keys Traceway keeps. Traceway hands it back when the
 * rider disconnects or deletes their account.
 */
interface GrantedAccess
{
    /**
     * New keys for ones that ran out. A service without refresh tokens returns the same ones.
     *
     * @throws ServiceRefused when the rider has to connect again
     * @throws ServiceUnavailable when the service cannot be reached
     */
    public function refresh(ServiceTokens $serviceTokens): ServiceTokens;

    /**
     * Withdraws Traceway's access. A failure here does not stop the rider from disconnecting.
     */
    public function revoke(ServiceTokens $serviceTokens): void;

    /**
     * Lets go of keys that newer ones for the same rider replaced, such as after another log in. Only a service
     * that can end one key on its own does anything: where the only way out ends the whole grant, the new keys
     * would stop working too.
     *
     * @throws ServiceRefused when the service no longer accepts the old key
     * @throws ServiceUnavailable when the service cannot be reached
     */
    public function supersede(ServiceTokens $serviceTokens): void;
}
