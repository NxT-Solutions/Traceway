<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Sport;
use InvalidArgumentException;

/**
 * One leg of a saved multisport plan. A leg without a sport is the swim.
 */
final readonly class SavedLeg
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(
        public ?Sport $sport,
        public int $distanceMetres,
        public int $ascentMetres,
        public array $coordinates,
        public ?string $color = null,
        public ?RouteDetails $details = null,
    ) {
        throw_if($this->distanceMetres < 0 || $this->ascentMetres < 0, InvalidArgumentException::class, 'A leg cannot be shorter than nothing.');
        throw_if($this->sport instanceof Sport && count($this->coordinates) < 2, InvalidArgumentException::class, 'A land leg needs at least two points.');
        throw_if(! CoordinateLine::optional($this->coordinates) instanceof CoordinateLine, InvalidArgumentException::class, 'A leg needs a point.');
        throw_if($this->color !== null && preg_match('/^#[0-9a-f]{6}$/i', $this->color) !== 1, InvalidArgumentException::class, 'A leg colour is a six-digit hex colour.');
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function from(string $discipline, int $distanceMetres, int $ascentMetres, array $coordinates, ?string $color = null, ?RouteDetails $routeDetails = null): self
    {
        return new self(
            $discipline === 'swim' ? null : Sport::from($discipline),
            $distanceMetres,
            $ascentMetres,
            $coordinates,
            $color === null ? null : strtolower($color),
            $routeDetails,
        );
    }

    /**
     * The same leg with its climb, surfaces, turns, and stops. The climb
     * read from the ground replaces the one the planner sent.
     */
    public function withDetails(RouteDetails $routeDetails): self
    {
        $ascentMetres = $routeDetails->hasClimb() ? $routeDetails->ascentMetres : $this->ascentMetres;

        return new self($this->sport, $this->distanceMetres, $ascentMetres, $this->coordinates, $this->color, $routeDetails);
    }

    public function discipline(): string
    {
        return $this->sport instanceof Sport ? $this->sport->value : 'swim';
    }

    /**
     * @return array{discipline: string, distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>, color: string|null}
     */
    public function toArray(): array
    {
        return [
            'discipline' => $this->discipline(),
            'distance_metres' => $this->distanceMetres,
            'ascent_metres' => $this->ascentMetres,
            'coordinates' => $this->coordinates,
            'color' => $this->color,
        ];
    }

    /**
     * The leg as the legs column keeps it, with its details.
     *
     * @return array{discipline: string, distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>, color: string|null, details: array<string, mixed>|null}
     */
    public function toStored(): array
    {
        return [...$this->toArray(), 'details' => $this->details?->toArray()];
    }
}
