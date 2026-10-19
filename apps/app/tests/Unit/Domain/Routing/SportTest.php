<?php

declare(strict_types=1);

use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

it('starts each sport on its own ground', function (Sport $sport, Surface $surface): void {
    expect($sport->defaultSurface())->toBe($surface)
        ->and(Sport::defaultSurfaces()[$sport->value])->toBe($surface->value);
})->with([
    'road' => [Sport::Road, Surface::Paved],
    'gravel' => [Sport::Gravel, Surface::Mixed],
    'mountain bike' => [Sport::Mtb, Surface::Dirt],
    'run' => [Sport::Run, Surface::Paved],
    'trail run' => [Sport::TrailRun, Surface::Dirt],
    'walk' => [Sport::Walk, Surface::Mixed],
    'hike' => [Sport::Hike, Surface::Dirt],
]);

it('gives the planner a surface for every sport', function (): void {
    expect(array_keys(Sport::defaultSurfaces()))->toBe(array_column(Sport::cases(), 'value'));
});
