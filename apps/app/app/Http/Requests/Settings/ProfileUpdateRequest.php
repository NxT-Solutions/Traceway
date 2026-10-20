<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'ProfileUpdateRequest',
    description: 'The name and email of the signed-in person. A new email waits until the link sent to it is opened, and asking for one needs the current password, or a confirmation a moment ago for an account without one.',
    required: true,
    content: new JsonContent(
        required: ['name', 'email'],
        properties: [
            new Property(property: 'name', type: 'string', maxLength: 255),
            new Property(property: 'email', type: 'string', format: 'email'),
            new Property(property: 'current_password', description: 'Needed to ask for a new email, unless the rider confirmed it is them a moment ago. It is not returned.', type: 'string', format: 'password'),
        ],
        type: 'object',
    ),
)]
class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        abort_unless($user instanceof User, 403);

        return [
            ...$this->profileRules($user->id),
            'current_password' => ['nullable', 'string', 'current_password'],
        ];
    }

    /**
     * Moving the account to another email is how a stolen session becomes a stolen account, so it asks for the
     * password, or for a confirmation a moment ago with a passkey or a connected account.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();

            if (! $user instanceof User || ! $this->changesEmail($user) || $this->confirmedRecently() || $this->filled('current_password')) {
                return;
            }

            $validator->errors()->add('current_password', $user->password === null
                ? 'Confirm it is you first, then change your email.'
                : 'Enter your password to change your email.');
        }];
    }

    private function changesEmail(User $user): bool
    {
        return $this->string('email')->trim()->lower()->toString() !== mb_strtolower($user->email);
    }

    private function confirmedRecently(): bool
    {
        $confirmedAt = $this->session()->get('auth.password_confirmed_at', 0);
        $timeout = config('auth.password_timeout', 10800);

        return is_numeric($confirmedAt) && is_numeric($timeout) && (int) $confirmedAt >= now()->getTimestamp() - (int) $timeout;
    }
}
