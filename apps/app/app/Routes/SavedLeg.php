<?php

declare(strict_types=1);

namespace App\Routes;

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
    ) {
        throw_if($this->distanceMetres < 0 || $this->ascentMetres < 0, InvalidArgumentException::class, 'A leg cannot be shorter than nothing.');
        throw_if($this->sport instanceof Sport && count($this->coordinates) < 2, InvalidArgumentException::class, 'A land leg needs at least two points.');
        throw_if(! CoordinateLine::optional($this->coordinates) instanceof CoordinateLine, InvalidArgumentException::class, 'A leg needs a point.');
        throw_if($this->color !== null && preg_match('/^#[0-9a-f]{6}$/i', $this->color) !== 1, InvalidArgumentException::class, 'A leg colour is a six-digit hex colour.');
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function from(string $discipline, int $distanceMetres, int $ascentMetres, array $coordinates, ?string $color = null): self
    {
        return new self(
            $discipline === 'swim' ? null : Sport::from($discipline),
            $distanceMetres,
            $ascentMetres,
            $coordinates,
            $color === null ? null : strtolower($color),
        );
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
}
