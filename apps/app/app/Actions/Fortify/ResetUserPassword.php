<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Account\RiderId;
use App\Commands\ChangePasswordCommand;
use App\Commands\ClaimUnconfirmedAccountCommand;
use App\Concerns\PasswordValidationRules;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private DispatchesCommands $dispatchesCommands) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $password = $input['password'] ?? null;

        abort_unless(is_string($password), 422);

        // The reset link proves the address. Until someone did that, whoever opened the account may not own it,
        // so nothing they added stays. Only an email that was never confirmed counts: a rider who changed theirs
        // was welcomed when they first confirmed one, and keeps what they set up.
        if ($user->email_verified_at === null && $user->welcomed_at === null) {
            $this->dispatchesCommands->dispatch(new ClaimUnconfirmedAccountCommand(new RiderId($user->id)));
        }

        // Whoever knew the old password, or held a session, is signed out everywhere.
        $this->dispatchesCommands->dispatch(new ChangePasswordCommand(
            riderId: new RiderId($user->id),
            password: $password,
            endSessions: true,
        ));
    }
}
