<?php

declare(strict_types=1);

use App\Domain\Routing\RoutingEngine;
use App\Integrations\GraphHopper\GraphHopperRoutingEngine;
use App\Integrations\Osrm\OsrmRoutingEngine;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use Inertia\Testing\AssertableInertia as Assert;

it('welcomes a rider with the traceway home', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('name', 'Traceway')
            ->has('auth.user')
        );
});

it('resolves route generation to the filter-aware router by default', function () {
    config(['services.router' => 'valhalla']);

    expect(app(RoutingEngine::class))->toBeInstanceOf(ValhallaRoutingEngine::class);
});

it('resolves route generation to osrm when that driver is selected', function () {
    config(['services.router' => 'osrm']);

    expect(app(RoutingEngine::class))->toBeInstanceOf(OsrmRoutingEngine::class);
});

it('resolves route generation to graphhopper when that driver is selected', function () {
    config(['services.router' => 'graphhopper', 'services.graphhopper.url' => 'http://graphhopper:8989']);

    $engine = app(RoutingEngine::class);

    expect($engine)->toBeInstanceOf(GraphHopperRoutingEngine::class)
        ->and($engine->connector()->resolveBaseUrl())->toBe('http://graphhopper:8989');
});
