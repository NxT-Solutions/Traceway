<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

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

        if (! is_string($name) || ! is_string($email) || ! is_string($password)) {
            abort(422);
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }
}
