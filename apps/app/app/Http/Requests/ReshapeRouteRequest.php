<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\Reshape;
use App\Domain\Routing\RouteChange;
use App\Domain\Routing\RouteChangeKind;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Validation\LineWithinReach;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'ReshapeRouteRequest',
    description: 'A drawn route, the points it passes, and one change to it.',
    required: true,
    content: new JsonContent(
        required: ['sport', 'surface', 'elevation', 'mode', 'line', 'waypoints', 'change'],
        properties: [
            new Property(property: 'sport', type: 'string', enum: ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
            new Property(property: 'surface', type: 'string', enum: ['paved', 'mixed', 'dirt']),
            new Property(property: 'elevation', type: 'string', enum: ['minimize', 'neutral', 'maximize']),
            new Property(property: 'roads', type: 'string', enum: ['quiet', 'any']),
            new Property(property: 'distance_metres', description: 'The length asked for, for a loop.', type: 'integer', maximum: 300000, minimum: 1000),
            new Property(property: 'mode', description: 'A loop Traceway planned, or a route the rider built from points.', type: 'string', enum: ['loop', 'build']),
            new Property(property: 'finish', description: 'A built route whose last leg is the way home Traceway found.', type: 'boolean'),
            new Property(property: 'line', ref: '#/components/schemas/CoordinateLine'),
            new Property(property: 'waypoints', ref: '#/components/schemas/CoordinateLine'),
            new Property(
                property: 'change',
                required: ['kind'],
                properties: [
                    new Property(property: 'kind', type: 'string', enum: ['move', 'insert', 'remove', 'reroute']),
                    new Property(property: 'index', description: 'The waypoint moved, put in, or taken out.', type: 'integer'),
                    new Property(property: 'latitude', type: 'number'),
                    new Property(property: 'longitude', type: 'number'),
                    new Property(property: 'legs', description: 'The legs to plan again, for a reroute.', type: 'array', items: new Items(type: 'integer')),
                ],
                type: 'object',
            ),
        ],
        type: 'object',
    ),
)]
class ReshapeRouteRequest extends FormRequest
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
            'distance_metres' => ['sometimes', 'integer', 'min:1000', 'max:300000'],
            'mode' => ['required', Rule::in(['loop', 'build'])],
            'finish' => ['sometimes', 'boolean'],
            'line' => ['required', 'array', 'min:2', 'max:8000', new LineWithinReach],
            'line.*' => ['array', 'size:2'],
            'line.*.0' => ['numeric', 'between:-180,180'],
            'line.*.1' => ['numeric', 'between:-90,90'],
            'waypoints' => ['required', 'array', 'min:2', 'max:'.Reshape::MOST_WAYPOINTS],
            'waypoints.*' => ['array', 'size:2'],
            'waypoints.*.0' => ['numeric', 'between:-180,180'],
            'waypoints.*.1' => ['numeric', 'between:-90,90'],
            'change' => ['required', 'array'],
            'change.kind' => ['required', Rule::enum(RouteChangeKind::class)],
            'change.index' => ['required_unless:change.kind,reroute', 'integer', 'min:0', 'max:'.Reshape::MOST_WAYPOINTS],
            'change.latitude' => ['required_if:change.kind,move,insert', 'numeric', 'between:-90,90'],
            'change.longitude' => ['required_if:change.kind,move,insert', 'numeric', 'between:-180,180'],
            'change.legs' => ['required_if:change.kind,reroute', 'array', 'min:1', 'max:3'],
            'change.legs.*' => ['integer', 'min:0', 'max:'.Reshape::MOST_WAYPOINTS],
        ];
    }

    public function toQuery(): RouteQuery
    {
        /** @var array{sport: string, surface: string, elevation: string, roads?: string, distance_metres?: int, mode: string} $validated */
        $validated = $this->validated();
        $start = $this->waypoints()[0];

        return new RouteQuery(
            sport: Sport::from($validated['sport']),
            shape: $this->isLoop() ? Shape::Loop : Shape::PointToPoint,
            distanceMetres: $validated['distance_metres'] ?? 5_000,
            surface: Surface::from($validated['surface']),
            elevation: ElevationBias::from($validated['elevation']),
            startLatitude: $start[1],
            startLongitude: $start[0],
            preferQuiet: ($validated['roads'] ?? 'quiet') === 'quiet',
        );
    }

    public function isLoop(): bool
    {
        return $this->string('mode')->toString() === 'loop';
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function line(): array
    {
        return $this->points('line');
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function waypoints(): array
    {
        return $this->points('waypoints');
    }

    public function change(): RouteChange
    {
        /** @var array{kind: string, index?: int, latitude?: float|int, longitude?: float|int, legs?: list<int>} $change */
        $change = $this->validated('change');
        $index = $change['index'] ?? 0;
        $point = isset($change['latitude'], $change['longitude']) ? [(float) $change['longitude'], (float) $change['latitude']] : [0.0, 0.0];

        return match (RouteChangeKind::from($change['kind'])) {
            RouteChangeKind::Move => RouteChange::move($index, $point),
            RouteChangeKind::Insert => RouteChange::insert($index, $point),
            RouteChangeKind::Remove => RouteChange::remove($index),
            RouteChangeKind::Reroute => RouteChange::reroute(array_map(intval(...), $change['legs'] ?? [])),
        };
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private function points(string $key): array
    {
        /** @var list<array{0: float|int, 1: float|int}> $points */
        $points = $this->validated($key);

        return array_map(fn (array $point): array => [(float) $point[0], (float) $point[1]], $points);
    }
}
