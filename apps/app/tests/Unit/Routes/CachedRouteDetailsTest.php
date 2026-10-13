<?php

declare(strict_types=1);

use App\Domain\Routing\Sport;
use App\Routes\CachedRouteDetails;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\Fakes\FixedRouteDetails;
use Tests\Fakes\UnavailableRouteDetails;

it('reads a line from the map once an hour', function (): void {
    $fixedRouteDetails = new FixedRouteDetails;
    $cachedRouteDetails = new CachedRouteDetails($fixedRouteDetails, new Repository(new ArrayStore));
    $line = [[4.9, 51.1], [4.93, 51.12], [4.9, 51.1]];

    $routeDetails = $cachedRouteDetails->for(Sport::Road, $line);
    $second = $cachedRouteDetails->for(Sport::Road, $line);
    $cachedRouteDetails->road(Sport::Run, $line);

    expect($second)->toEqual($routeDetails)
        ->and($second->stops)->toHaveCount(1)
        ->and($fixedRouteDetails->asked)->toHaveCount(3);
});

it('keeps the road and leaves out the stops when the stop finder does not answer', function (): void {
    $cachedRouteDetails = new CachedRouteDetails(new UnavailableRouteDetails, new Repository(new ArrayStore));

    expect(fn (): array => $cachedRouteDetails->stops(Sport::Road, [[4.9, 51.1], [4.93, 51.12]]))->toThrow(RuntimeException::class);
});
