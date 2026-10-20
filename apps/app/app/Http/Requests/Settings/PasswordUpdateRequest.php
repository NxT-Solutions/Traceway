<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'PasswordUpdateRequest',
    description: 'The current password and the replacement. Neither is returned. A rider who signed up with a connected account and has no password yet sets one without the current password, since the page already asked them to confirm it is them.',
    required: true,
    content: new JsonContent(
        required: ['password', 'password_confirmation'],
        properties: [
            new Property(property: 'current_password', type: 'string', format: 'password'),
            new Property(property: 'password', type: 'string', format: 'password'),
            new Property(property: 'password_confirmation', type: 'string', format: 'password'),
        ],
        type: 'object',
    ),
)]
class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->hasPassword() ? $this->currentPasswordRules() : ['prohibited'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * Without a password, the rider must have confirmed it is them a moment ago, with a passkey or a connected
     * account, or anyone at their open session could add a password and keep their way in.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->hasPassword()) {
                return;
            }

            $confirmedAt = $this->session()->get('auth.password_confirmed_at', 0);
            $timeout = config('auth.password_timeout', 10800);

            if (! is_numeric($confirmedAt) || ! is_numeric($timeout) || (int) $confirmedAt < now()->getTimestamp() - (int) $timeout) {
                $validator->errors()->add('password', 'Confirm it is you first, then set a password.');
            }
        }];
    }

    private function hasPassword(): bool
    {
        $user = $this->user();

        return ! $user instanceof User || $user->password !== null;
    }
}
