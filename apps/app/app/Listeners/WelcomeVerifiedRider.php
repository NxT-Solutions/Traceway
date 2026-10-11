<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Account\RiderId;
use App\Commands\WelcomeRiderCommand;
use App\Contracts\Bus\DispatchesCommands;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class WelcomeVerifiedRider
{
    public function __construct(private DispatchesCommands $dispatchesCommands) {}

    public function handle(Verified $verified): void
    {
        $user = $verified->user;

        if (! $user instanceof Authenticatable) {
            return;
        }

        $id = $user->getAuthIdentifier();

        if (! is_int($id)) {
            return;
        }

        $this->dispatchesCommands->dispatch(new WelcomeRiderCommand(new RiderId($id)));
    }
}
