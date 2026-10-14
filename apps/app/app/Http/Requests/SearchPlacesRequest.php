<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'SearchPlacesRequest',
    description: 'What a rider typed, and the middle of the map they look at.',
    required: true,
    content: new JsonContent(
        required: ['query', 'latitude', 'longitude'],
        properties: [
            new Property(property: 'query', type: 'string', maxLength: 120, minLength: 2),
            new Property(property: 'latitude', type: 'number', maximum: 90, minimum: -90),
            new Property(property: 'longitude', type: 'number', maximum: 180, minimum: -180),
            new Property(property: 'language', description: 'The language to name places in, like "nl" or "en-GB".', type: 'string', pattern: '^[a-z]{2}(-[A-Z]{2})?$'),
        ],
        type: 'object',
    ),
)]
class SearchPlacesRequest extends FormRequest
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
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'language' => ['nullable', 'string', 'regex:/^[a-z]{2}(-[A-Z]{2})?$/'],
        ];
    }

    /**
     * The language to name places in: the one asked for, or the app's own.
     */
    public function language(): string
    {
        $language = $this->string('language')->toString();

        return $language === '' ? app()->getLocale() : $language;
    }
}
