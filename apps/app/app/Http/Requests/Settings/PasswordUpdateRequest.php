<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'PasswordUpdateRequest',
    description: 'The current password and the replacement. Neither is returned.',
    required: true,
    content: new JsonContent(
        required: ['current_password', 'password', 'password_confirmation'],
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
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
