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

class PreviewRouteRequest extends FormRequest
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
            'distance_metres' => ['required', 'integer', 'min:1000', 'max:300000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'shape' => ['sometimes', Rule::enum(Shape::class)],
            'roads' => ['sometimes', Rule::in(['quiet', 'any'])],
            'via_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:via_longitude'],
            'via_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:via_latitude'],
        ];
    }

    public function toQuery(): RouteQuery
    {
        /** @var array{sport: string, surface: string, elevation: string, distance_metres: int, latitude: float|int, longitude: float|int, shape?: string, roads?: string, via_latitude?: float|int|null, via_longitude?: float|int|null} $validated */
        $validated = $this->validated();
        $viaLatitude = $validated['via_latitude'] ?? null;
        $viaLongitude = $validated['via_longitude'] ?? null;

        return new RouteQuery(
            sport: Sport::from($validated['sport']),
            shape: isset($validated['shape']) ? Shape::from($validated['shape']) : Shape::Loop,
            distanceMetres: $validated['distance_metres'],
            surface: Surface::from($validated['surface']),
            elevation: ElevationBias::from($validated['elevation']),
            startLatitude: (float) $validated['latitude'],
            startLongitude: (float) $validated['longitude'],
            viaLatitude: $viaLatitude === null ? null : (float) $viaLatitude,
            viaLongitude: $viaLongitude === null ? null : (float) $viaLongitude,
            preferQuiet: ($validated['roads'] ?? 'any') === 'quiet',
        );
    }
}
