<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domain\Routing\Place;
use App\Domain\Routing\PlaceFinder;
use InvalidArgumentException;

/**
 * Where a plan starts, from the words an AI tool passes on: a place name, an
 * address, or "latitude, longitude". Only the start comes from the words.
 * The route itself always comes from the routing engine.
 */
final readonly class StartPoint
{
    public function __construct(private PlaceFinder $placeFinder) {}

    /**
     * @throws InvalidArgumentException when the map knows no such place
     */
    public function resolve(string $search): Place
    {
        $search = trim($search);

        if (preg_match('/^(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/', $search, $match) === 1) {
            return new Place($search, (float) $match[1], (float) $match[2]);
        }

        return $this->placeFinder->find($search)
            ?? throw new InvalidArgumentException(sprintf('No place called "%s" on the map. Try a town and country, like "Westerlo, Belgium".', $search));
    }
}
