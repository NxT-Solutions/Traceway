<?php

declare(strict_types=1);

use App\Integrations\Valhalla\Requests\LocateRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

function snapConnector(MockResponse $response): MockClient
{
    $mock = new MockClient([
        LocateRequest::class => $response,
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);
    app()->instance(ValhallaConnector::class, $connector);

    return $mock;
}

it('snaps a ride point onto the nearest road', function () {
    $mock = snapConnector(MockResponse::make([
        [
            'edges' => [[
                'correlated_lat' => 51.0512,
                'correlated_lon' => 3.7214,
            ]],
        ],
    ]));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.05,
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertOk()
        ->assertJsonPath('latitude', 51.0512)
        ->assertJsonPath('longitude', 3.7214);

    $body = $mock->getLastPendingRequest()?->body()?->all();

    expect($body)->toMatchArray([
        'verbose' => true,
        'costing' => 'bicycle',
        'locations' => [[
            'lat' => 51.05,
            'lon' => 3.72,
            'radius' => 80,
        ]],
    ]);
});

it('snaps a hike onto a path a person can walk', function () {
    $mock = snapConnector(MockResponse::make([
        [
            'edges' => [[
                'correlated_lat' => 51.02,
                'correlated_lon' => 3.7,
            ]],
        ],
    ]));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.02,
            'longitude' => 3.7,
            'sport' => 'hike',
        ])
        ->assertOk();

    expect($mock->getLastPendingRequest()?->body()?->all()['costing'] ?? null)->toBe('pedestrian');
});

it('refuses a point that is not on a road or path', function () {
    snapConnector(MockResponse::make([
        ['edges' => []],
    ]));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.05,
            'longitude' => 3.72,
            'sport' => 'gravel',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No road or path is close enough to put a point there.');
});

it('rejects a snap without a place', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude']);
});

it('asks a guest to log in before snapping a point', function () {
    $this->postJson(route('routes.snap'), [])
        ->assertUnauthorized();
});
