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

class BuildRouteRequest extends FormRequest
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
            'distance_metres' => ['sometimes', 'integer', 'between:5000,200000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'follow' => ['required', 'boolean'],
            'finish' => ['required', 'boolean'],
            'points' => ['present', 'array', 'max:12'],
            'points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function toQuery(): RouteQuery
    {
        /** @var array{sport: string, surface: string, elevation: string, latitude: float|int, longitude: float|int, roads?: string, distance_metres?: int} $validated */
        $validated = $this->validated();

        return new RouteQuery(
            sport: Sport::from($validated['sport']),
            shape: Shape::PointToPoint,
            distanceMetres: $validated['distance_metres'] ?? 5_000,
            surface: Surface::from($validated['surface']),
            elevation: ElevationBias::from($validated['elevation']),
            startLatitude: (float) $validated['latitude'],
            startLongitude: (float) $validated['longitude'],
            preferQuiet: ($validated['roads'] ?? 'quiet') === 'quiet',
        );
    }

    /**
     * Start, then each tapped point, as longitude, latitude.
     *
     * @return list<array{0: float, 1: float}>
     */
    public function waypoints(): array
    {
        /** @var array{latitude: float|int, longitude: float|int, points: list<array{latitude: float|int, longitude: float|int}>} $validated */
        $validated = $this->validated();
        $points = [[(float) $validated['longitude'], (float) $validated['latitude']]];

        foreach ($validated['points'] as $point) {
            $points[] = [(float) $point['longitude'], (float) $point['latitude']];
        }

        return $points;
    }
}
