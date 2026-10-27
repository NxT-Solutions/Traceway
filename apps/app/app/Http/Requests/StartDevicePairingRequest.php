<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Account\DeviceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'StartDevicePairingRequest',
    description: 'The device app that asks for a code.',
    required: false,
    content: new JsonContent(
        properties: [
            new Property(property: 'provider', type: 'string', default: 'garmin', enum: ['garmin']),
        ],
        type: 'object',
    ),
)]
class StartDevicePairingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', Rule::enum(DeviceProvider::class)],
        ];
    }

    public function provider(): DeviceProvider
    {
        $provider = $this->validated('provider');

        return is_string($provider) ? DeviceProvider::from($provider) : DeviceProvider::Garmin;
    }
}
