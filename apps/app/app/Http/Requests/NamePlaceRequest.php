<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'NamePlaceRequest',
    description: 'A point on the map.',
    required: true,
    content: new JsonContent(
        required: ['latitude', 'longitude'],
        properties: [
            new Property(property: 'latitude', type: 'number', maximum: 90, minimum: -90),
            new Property(property: 'longitude', type: 'number', maximum: 180, minimum: -180),
        ],
        type: 'object',
    ),
)]
class NamePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
