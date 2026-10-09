<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Account\RiderId;
use App\Commands\ChangePasswordCommand;
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

        $this->dispatchesCommands->dispatch(new ChangePasswordCommand(
            riderId: new RiderId($user->id),
            password: $password,
        ));
    }
}
