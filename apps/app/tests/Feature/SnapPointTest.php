<?php

declare(strict_types=1);

use App\Integrations\Valhalla\ValhallaConnector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Saloon\Config;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Config::sleepUsing(sleepHandler: null);
});

/**
 * The routing engine answers each lookup in turn with the next response.
 */
function snapConnector(MockResponse ...$mockResponses): MockClient
{
    $mock = new MockClient(array_values($mockResponses));
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    app()->instance(ValhallaConnector::class, $connector);

    return $mock;
}

it('snaps a ride point onto the nearest road', function (): void {
    $mockClient = snapConnector(MockResponse::make([
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

    $body = $mockClient->getLastPendingRequest()?->body()?->all();

    expect($body)->toMatchArray([
        'verbose' => true,
        'costing' => 'bicycle',
        'costing_options' => ['bicycle' => ['bicycle_type' => 'Road', 'avoid_bad_surfaces' => 1.0]],
        'locations' => [[
            'lat' => 51.05,
            'lon' => 3.72,
            'radius' => 80,
        ]],
    ]);
});

it('snaps a hike onto a path a person can walk', function (): void {
    $mockClient = snapConnector(MockResponse::make([
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

    $body = $mockClient->getLastPendingRequest()?->body()?->all();

    // A hike's route may take a mountain path, so its point may land on one.
    expect($body['costing'] ?? null)->toBe('pedestrian')
        ->and($body['costing_options'] ?? null)->toBe(['pedestrian' => ['walking_speed' => 5.0, 'max_hiking_difficulty' => 2]]);
});

it('snaps a dirt ride onto rough ground its route may use', function (): void {
    $mockClient = snapConnector(MockResponse::make([
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
            'sport' => 'road',
            'surface' => 'dirt',
        ])
        ->assertOk();

    expect($mockClient->getLastPendingRequest()?->body()?->all()['costing_options'] ?? null)
        ->toBe(['bicycle' => ['bicycle_type' => 'Road', 'avoid_bad_surfaces' => 0.0]]);
});

it('refuses a point that is not on a road or path', function (): void {
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

it('asks the routing engine again after it turns the lookup away', function (): void {
    $slept = [];
    Config::sleepUsing(function (int $microseconds) use (&$slept): void {
        $slept[] = $microseconds;
    });
    $mockClient = snapConnector(
        MockResponse::make(['error' => 'Too many requests'], 429),
        MockResponse::make([
            [
                'edges' => [[
                    'correlated_lat' => 51.0512,
                    'correlated_lon' => 3.7214,
                ]],
            ],
        ]),
    );

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.05,
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertOk()
        ->assertJsonPath('latitude', 51.0512);

    $mockClient->assertSentCount(2);
    expect($slept)->toBe([1_000_000]);
});

it('says the map is busy, not that no road is near, when the routing engine keeps failing', function (): void {
    Exceptions::fake();
    Config::sleepUsing(static function (): void {});
    $mockClient = snapConnector(
        MockResponse::make(['error' => 'Bad gateway'], 502),
        MockResponse::make(['error' => 'Service unavailable'], 503),
    );

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.05,
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'The map is busy. Try again in a moment.');

    $mockClient->assertSentCount(2);
    // A busy router is not an error in Traceway.
    Exceptions::assertNothingReported();
});

it('says the map is busy, without asking again, when the routing engine refuses the request', function (): void {
    $slept = [];
    Config::sleepUsing(function (int $microseconds) use (&$slept): void {
        $slept[] = $microseconds;
    });
    $mockClient = snapConnector(
        MockResponse::make(['error' => 'Bad request'], 400),
        MockResponse::make(['error' => 'Bad request'], 400),
    );

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.05,
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'The map is busy. Try again in a moment.');

    $mockClient->assertSentCount(1);
    expect($slept)->toBe([]);
});

it('says the map is busy when the routing engine answers something that is not a road', function (): void {
    Exceptions::fake();
    snapConnector(MockResponse::make('<html>Maintenance</html>', 200));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.05,
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertServiceUnavailable();

    Exceptions::assertReportedCount(1);
});

it('says the map is busy after a timeout, without asking again', function (): void {
    Config::sleepUsing(static function (): void {});
    $attempts = 0;
    $timeout = function (PendingRequest $pendingRequest) use (&$attempts): FatalRequestException {
        $attempts++;

        return new FatalRequestException(new RuntimeException('Timed out'), $pendingRequest);
    };
    snapConnector(MockResponse::make()->throw($timeout), MockResponse::make()->throw($timeout));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.05,
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'The map is busy. Try again in a moment.');

    expect($attempts)->toBe(1);
});

it('reports only the kind of failure, never where the rider is', function (): void {
    Exceptions::fake();
    snapConnector(
        MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('cURL error 28: timed out at 51.0573,3.7291'), $pendingRequest)),
    );

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'latitude' => 51.0573,
            'longitude' => 3.7291,
            'sport' => 'road',
        ])
        ->assertServiceUnavailable();

    Exceptions::assertReported(fn (RuntimeException $runtimeException): bool => ! str_contains($runtimeException->getMessage(), '51.0573'));
    Exceptions::assertReportedCount(1);
    Exceptions::assertNotReported(FatalRequestException::class);
});

it('rejects a snap without a place', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.snap'), [
            'longitude' => 3.72,
            'sport' => 'road',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude']);
});

it('asks a guest to log in before snapping a point', function (): void {
    $this->postJson(route('routes.snap'), [])
        ->assertUnauthorized();
});
