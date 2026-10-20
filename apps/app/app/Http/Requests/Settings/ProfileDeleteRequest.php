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
    request: 'ProfileDeleteRequest',
    description: 'Confirms the deletion. The password is not returned. A rider without a password confirms it is them with a passkey or a connected account first, and sends none.',
    required: false,
    content: new JsonContent(
        properties: [
            new Property(property: 'password', description: 'Confirms the deletion. It is not stored in the response.', type: 'string', format: 'password'),
        ],
        type: 'object',
    ),
)]
class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->hasPassword()) {
            return ['password' => $this->currentPasswordRules()];
        }

        return [];
    }

    /**
     * Without a password, the rider must have confirmed it is them a moment ago, with a passkey or a connected
     * account, the same way the security page asks.
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
                $validator->errors()->add('password', 'Confirm it is you first, then delete the account.');
            }
        }];
    }

    private function hasPassword(): bool
    {
        $user = $this->user();

        return ! $user instanceof User || $user->password !== null;
    }
}
