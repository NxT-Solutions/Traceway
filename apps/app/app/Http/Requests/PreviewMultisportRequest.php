<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\LandLeg;
use App\Domain\Routing\Layout;
use App\Domain\Routing\MultisportQuery;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Domain\Routing\SwimLeg;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'PreviewMultisportRequest',
    description: 'The transition, an optional swim, and the land legs in order.',
    required: true,
    content: new JsonContent(
        required: ['layout', 'latitude', 'longitude', 'legs'],
        properties: [
            new Property(property: 'layout', type: 'string', enum: ['loops', 'chain']),
            new Property(property: 'latitude', type: 'number', maximum: 90, minimum: -90),
            new Property(property: 'longitude', type: 'number', maximum: 180, minimum: -180),
            new Property(
                property: 'swim',
                required: ['coordinates'],
                properties: [
                    new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
                ],
                type: 'object',
                nullable: true,
            ),
            new Property(
                property: 'legs',
                type: 'array',
                items: new Items(
                    required: ['sport', 'surface', 'elevation', 'distance_metres'],
                    properties: [
                        new Property(property: 'sport', type: 'string', enum: ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
                        new Property(property: 'surface', type: 'string', enum: ['paved', 'mixed', 'dirt']),
                        new Property(property: 'elevation', type: 'string', enum: ['minimize', 'neutral', 'maximize']),
                        new Property(property: 'roads', type: 'string', enum: ['quiet', 'any']),
                        new Property(property: 'distance_metres', type: 'integer', maximum: 300000, minimum: 1000),
                        new Property(property: 'finish_latitude', description: 'Required for a chain.', type: 'number', maximum: 90, minimum: -90),
                        new Property(property: 'finish_longitude', description: 'Required for a chain.', type: 'number', maximum: 180, minimum: -180),
                    ],
                ),
                maxItems: 4,
                minItems: 1,
            ),
        ],
        type: 'object',
    ),
)]
class PreviewMultisportRequest extends FormRequest
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
            'layout' => ['required', Rule::enum(Layout::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'swim' => ['nullable', 'array'],
            'swim.coordinates' => ['required_with:swim', 'array', 'min:1', 'max:'.SwimLeg::MOST_POINTS],
            'swim.coordinates.*' => ['array', 'size:2'],
            'swim.coordinates.*.0' => ['numeric', 'between:-180,180'],
            'swim.coordinates.*.1' => ['numeric', 'between:-90,90'],
            'legs' => ['required', 'array', 'list', 'min:1', 'max:'.MultisportQuery::MOST_LAND_LEGS],
            'legs.*' => ['array'],
            'legs.*.sport' => ['required', Rule::enum(Sport::class)],
            'legs.*.surface' => ['required', Rule::enum(Surface::class)],
            'legs.*.elevation' => ['required', Rule::enum(ElevationBias::class)],
            'legs.*.roads' => ['sometimes', Rule::in(['quiet', 'any'])],
            'legs.*.distance_metres' => ['required', 'integer', 'min:1000', 'max:300000'],
            'legs.*.finish_latitude' => ['required_if:layout,chain', 'nullable', 'numeric', 'between:-90,90'],
            'legs.*.finish_longitude' => ['required_if:layout,chain', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function toQuery(): MultisportQuery
    {
        /** @var array{layout: string, latitude: float|int, longitude: float|int, swim?: array{coordinates: list<array{0: float|int, 1: float|int}>}|null, legs: list<array{sport: string, surface: string, elevation: string, roads?: string, distance_metres: int, finish_latitude?: float|int|null, finish_longitude?: float|int|null}>} $validated */
        $validated = $this->validated();
        $swim = $validated['swim'] ?? null;
        $legs = [];

        foreach ($validated['legs'] as $leg) {
            $finishLatitude = $leg['finish_latitude'] ?? null;
            $finishLongitude = $leg['finish_longitude'] ?? null;

            $legs[] = new LandLeg(
                sport: Sport::from($leg['sport']),
                distanceMetres: $leg['distance_metres'],
                surface: Surface::from($leg['surface']),
                elevation: ElevationBias::from($leg['elevation']),
                preferQuiet: ($leg['roads'] ?? 'any') === 'quiet',
                finish: $finishLatitude === null || $finishLongitude === null
                    ? null
                    : [(float) $finishLongitude, (float) $finishLatitude],
            );
        }

        return new MultisportQuery(
            layout: Layout::from($validated['layout']),
            transitionLatitude: (float) $validated['latitude'],
            transitionLongitude: (float) $validated['longitude'],
            legs: $legs,
            swim: $swim === null ? null : new SwimLeg(array_map(
                fn (array $point): array => [(float) $point[0], (float) $point[1]],
                $swim['coordinates'],
            )),
        );
    }
}
