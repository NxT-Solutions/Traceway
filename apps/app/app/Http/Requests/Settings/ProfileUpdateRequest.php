<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'ProfileUpdateRequest',
    description: 'The name and email of the signed-in person.',
    required: true,
    content: new JsonContent(
        required: ['name', 'email'],
        properties: [
            new Property(property: 'name', type: 'string', maxLength: 255),
            new Property(property: 'email', type: 'string', format: 'email'),
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

        return $this->profileRules($user->id);
    }
}
