<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'StoreSavedRouteRequest',
    description: 'The route the signed-in person wants to keep.',
    required: true,
    content: new JsonContent(
        required: ['name', 'sport', 'surface', 'elevation', 'roads', 'distance_metres', 'ascent_metres', 'coordinates'],
        properties: [
            new Property(property: 'name', type: 'string', maxLength: 80),
            new Property(property: 'sport', type: 'string', enum: ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
            new Property(property: 'surface', type: 'string', enum: ['paved', 'mixed', 'dirt']),
            new Property(property: 'elevation', type: 'string', enum: ['minimize', 'neutral', 'maximize']),
            new Property(property: 'roads', type: 'string', enum: ['quiet', 'any']),
            new Property(property: 'distance_metres', type: 'integer'),
            new Property(property: 'ascent_metres', type: 'integer'),
            new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
        ],
        type: 'object',
    ),
)]
class StoreSavedRouteRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'sport' => ['required', Rule::enum(Sport::class)],
            'surface' => ['required', Rule::enum(Surface::class)],
            'elevation' => ['required', Rule::enum(ElevationBias::class)],
            'roads' => ['required', Rule::in(['quiet', 'any'])],
            'distance_metres' => ['required', 'integer', 'min:0', 'max:500000'],
            'ascent_metres' => ['required', 'integer', 'min:0', 'max:20000'],
            ...$this->lineRules('coordinates', required: true),
            ...$this->lineRules('user_coordinates', required: false),
            ...$this->lineRules('finish_coordinates', required: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineRules(string $key, bool $required): array
    {
        $presence = $required ? ['required', 'array', 'min:2'] : ['sometimes', 'nullable', 'array'];

        return [
            $key => [...$presence, 'max:20000'],
            $key.'.*' => ['array', 'size:2'],
            $key.'.*.0' => ['numeric', 'between:-180,180'],
            $key.'.*.1' => ['numeric', 'between:-90,90'],
        ];
    }
}
