<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Account\Email;
use App\Account\Rider;
use App\Account\RiderName;
use App\Commands\OpenAccountCommand;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    public function __construct(private DispatchesCommands $dispatchesCommands) {}

    /**
     * Validate and create a newly registered user.
     *
     * Fortify's contract types this as an untyped array. The shape is what
     * this action actually accepts; PHPStan's contravariance warning for that
     * mismatch is ignored in phpstan.neon.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        abort_if(! is_string($name) || ! is_string($email) || ! is_string($password), 422);

        $rider = $this->dispatchesCommands->dispatch(new OpenAccountCommand(
            name: new RiderName($name),
            email: new Email($email),
            password: $password,
        ));

        abort_unless($rider instanceof Rider, 500);

        $user = User::query()->find($rider->id->value);

        abort_unless($user instanceof User, 500);

        return $user;
    }
}
