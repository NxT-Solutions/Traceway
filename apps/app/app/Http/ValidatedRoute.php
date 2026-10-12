<?php

declare(strict_types=1);

namespace App\Http;

use App\Account\RiderId;
use App\Commands\SaveRouteCommand;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\Layout;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Routes\CoordinateLine;
use App\Routes\RouteName;
use App\Routes\SavedLeg;
use InvalidArgumentException;

/**
 * Narrows a validated save payload into the command. The form request already
 * checked the rules. This only gives the command a real type.
 */
final class ValidatedRoute
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function command(int $riderId, array $data): SaveRouteCommand
    {
        return new SaveRouteCommand(
            riderId: new RiderId($riderId),
            name: new RouteName(self::text($data, 'name')),
            sport: Sport::from(self::text($data, 'sport')),
            surface: Surface::from(self::text($data, 'surface')),
            elevation: ElevationBias::from(self::text($data, 'elevation')),
            roads: self::text($data, 'roads'),
            distanceMetres: self::whole($data, 'distance_metres'),
            ascentMetres: self::whole($data, 'ascent_metres'),
            coordinates: CoordinateLine::ride(self::line($data, 'coordinates')),
            userCoordinates: self::optionalLine($data, 'user_coordinates'),
            finishCoordinates: self::optionalLine($data, 'finish_coordinates'),
            layout: isset($data['layout']) ? Layout::from(self::text($data, 'layout')) : null,
            legs: self::legs($data),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<SavedLeg>
     */
    private static function legs(array $data): array
    {
        $value = $data['legs'] ?? null;

        if ($value === null) {
            return [];
        }

        abort_if(! is_array($value) || ! array_is_list($value), 422);

        $legs = [];

        foreach ($value as $leg) {
            abort_unless(is_array($leg), 422);

            /** @var array<string, mixed> $leg */
            try {
                $legs[] = SavedLeg::from(
                    self::text($leg, 'discipline'),
                    self::whole($leg, 'distance_metres'),
                    self::whole($leg, 'ascent_metres'),
                    self::line($leg, 'coordinates'),
                );
            } catch (InvalidArgumentException) {
                abort(422);
            }
        }

        return $legs;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        abort_unless(is_string($value), 422);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function whole(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        abort(422);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{0: float, 1: float}>
     */
    private static function line(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        abort_if(! is_array($value) || ! array_is_list($value), 422);

        $line = [];

        foreach ($value as $point) {
            abort_if(! is_array($point) || ! array_key_exists(0, $point) || ! array_key_exists(1, $point), 422);

            $longitude = $point[0];
            $latitude = $point[1];

            abort_if(! is_numeric($longitude) || ! is_numeric($latitude), 422);

            $line[] = [(float) $longitude, (float) $latitude];
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalLine(array $data, string $key): ?CoordinateLine
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return CoordinateLine::optional(self::line($data, $key));
    }
}
