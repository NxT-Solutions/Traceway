<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Account\PairingCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'LinkDeviceRequest',
    description: 'The code on the device and a name for it.',
    required: true,
    content: new JsonContent(
        required: ['code', 'label'],
        properties: [
            new Property(property: 'code', description: 'Six letters and digits. Spaces and case do not matter.', type: 'string'),
            new Property(property: 'label', type: 'string', maxLength: 40),
        ],
        type: 'object',
    ),
)]
class LinkDeviceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:12', function (string $attribute, mixed $value, Closure $fail): void {
                $code = is_string($value) ? strtoupper(str_replace([' ', '-'], '', $value)) : '';

                if (strlen($code) !== 6 || strspn($code, PairingCode::ALPHABET) !== 6) {
                    $fail('The code is the six letters and digits on the device.');
                }
            }],
            'label' => ['required', 'string', 'max:40'],
        ];
    }
}
