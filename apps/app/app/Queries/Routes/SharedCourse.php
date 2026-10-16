<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Sport;
use App\Routes\SavedLeg;

final readonly class SharedCourse
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  list<SavedLeg>  $legs
     */
    public function __construct(
        public string $name,
        public Sport $sport,
        public array $coordinates,
        public array $legs = [],
        public ?RouteDetails $details = null,
        public int $distanceMetres = 0,
        public int $ascentMetres = 0,
        public ?string $updatedAt = null,
    ) {}
}
