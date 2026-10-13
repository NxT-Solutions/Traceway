<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'RedoImportedRouteRequest',
    description: 'An imported line and how to ride it again.',
    required: true,
    content: new JsonContent(
        required: ['sport', 'surface', 'elevation', 'coordinates', 'mode'],
        properties: [
            new Property(property: 'sport', type: 'string', enum: ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
            new Property(property: 'surface', type: 'string', enum: ['paved', 'mixed', 'dirt']),
            new Property(property: 'elevation', type: 'string', enum: ['minimize', 'neutral', 'maximize']),
            new Property(property: 'roads', type: 'string', enum: ['quiet', 'any']),
            new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
            new Property(property: 'mode', description: 'keep the line, reverse it, or start it from the pin.', type: 'string', enum: ['keep', 'reverse', 'from_start']),
            new Property(property: 'name', description: 'The name read from the file.', type: 'string', maxLength: 80),
            new Property(property: 'latitude', description: 'The start pin, for from_start.', type: 'number'),
            new Property(property: 'longitude', description: 'The start pin, for from_start.', type: 'number'),
        ],
        type: 'object',
    ),
)]
class RedoImportedRouteRequest extends FormRequest
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
            'sport' => ['required', Rule::enum(Sport::class)],
            'surface' => ['required', Rule::enum(Surface::class)],
            'elevation' => ['required', Rule::enum(ElevationBias::class)],
            'roads' => ['sometimes', Rule::in(['quiet', 'any'])],
            'coordinates' => ['required', 'array', 'min:2', 'max:8000'],
            'coordinates.*' => ['array', 'size:2'],
            'coordinates.*.0' => ['numeric', 'between:-180,180'],
            'coordinates.*.1' => ['numeric', 'between:-90,90'],
            'mode' => ['required', Rule::in(['keep', 'reverse', 'from_start'])],
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'latitude' => ['required_if:mode,from_start', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:mode,from_start', 'numeric', 'between:-180,180'],
        ];
    }

    public function courseName(): string
    {
        $name = $this->validated('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : 'Imported route';
    }

    public function mode(): string
    {
        return $this->string('mode')->toString();
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function coordinates(): array
    {
        /** @var list<array{0: float|int, 1: float|int}> $coordinates */
        $coordinates = $this->validated('coordinates');
        $points = [];

        foreach ($coordinates as $coordinate) {
            $points[] = [(float) $coordinate[0], (float) $coordinate[1]];
        }

        return $points;
    }

    /**
     * The start pin, or the start of the line when there is no pin.
     *
     * @return array{0: float, 1: float}
     */
    public function start(): array
    {
        $latitude = $this->validated('latitude');
        $longitude = $this->validated('longitude');

        if (is_numeric($latitude) && is_numeric($longitude)) {
            return [(float) $longitude, (float) $latitude];
        }

        return $this->coordinates()[0];
    }

    public function toQuery(): RouteQuery
    {
        /** @var array{sport: string, surface: string, elevation: string, roads?: string} $validated */
        $validated = $this->validated();
        $start = $this->start();

        return new RouteQuery(
            sport: Sport::from($validated['sport']),
            shape: Shape::PointToPoint,
            distanceMetres: 5_000,
            surface: Surface::from($validated['surface']),
            elevation: ElevationBias::from($validated['elevation']),
            startLatitude: $start[1],
            startLongitude: $start[0],
            preferQuiet: ($validated['roads'] ?? 'quiet') === 'quiet',
        );
    }
}
