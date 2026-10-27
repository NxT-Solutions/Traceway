<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'ClaimDevicePairingRequest',
    description: 'The secret the device got when it opened the pairing.',
    required: true,
    content: new JsonContent(
        required: ['secret'],
        properties: [
            new Property(property: 'secret', type: 'string', maxLength: 48, minLength: 48),
        ],
        type: 'object',
    ),
)]
class ClaimDevicePairingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'secret' => ['required', 'string', 'size:48'],
        ];
    }

    public function secret(): string
    {
        $secret = $this->validated('secret');

        throw_unless(is_string($secret), InvalidArgumentException::class, 'A pairing secret is required.');

        return $secret;
    }
}
