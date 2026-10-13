<?php

declare(strict_types=1);

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * Traces straight through the points it is given, and remembers them.
 */
function tracingEngine(bool $fails = false): RoutingEngine
{
    return new class($fails) implements RoutingEngine
    {
        /** @var list<list<array{0: float, 1: float}>> */
        public array $traced = [];

        public function __construct(private readonly bool $fails) {}

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            throw new LogicException('Not used.');
        }

        public function candidates(RouteQuery $routeQuery): array
        {
            throw new LogicException('Not used.');
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            throw_if($this->fails, RuntimeException::class, 'No road.');

            $this->traced[] = $waypoints;

            return new GeneratedRoute($routeQuery, $waypoints, 3_000, 0);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            throw new LogicException('Not used.');
        }
    };
}

/**
 * @return array<string, mixed>
 */
function redoPayload(string $mode): array
{
    return [
        'sport' => 'road',
        'surface' => 'paved',
        'elevation' => 'neutral',
        'name' => 'Club loop',
        'coordinates' => [[4.0, 51.0], [4.015, 51.0], [4.015, 51.01], [4.0, 51.01], [4.0, 51.0]],
        'mode' => $mode,
        'latitude' => 51.0102,
        'longitude' => 4.0152,
    ];
}

it('opens a GPX file in the planner', function (): void {
    $file = UploadedFile::fake()->createWithContent('club-loop.gpx', '<gpx><trk><name>Club loop</name><trkseg><trkpt lat="51.0" lon="4.0"/><trkpt lat="51.0" lon="4.01"/><trkpt lat="51.01" lon="4.01"/></trkseg></trk></gpx>');

    $this->actingAs(User::factory()->create())
        ->post(route('routes.import'), ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('name', 'Club loop')
        ->assertJsonPath('coordinates.0', [4, 51])
        ->assertJsonCount(3, 'coordinates')
        ->assertJsonPath('ascent_metres', 0);
});

it('refuses a file that is not a GPX route', function (string $name, string $contents): void {
    $file = UploadedFile::fake()->createWithContent($name, $contents);

    $this->actingAs(User::factory()->create())
        ->post(route('routes.import'), ['file' => $file], ['Accept' => 'application/json'])
        ->assertUnprocessable();
})->with([
    'a photo' => ['photo.jpg', 'not a route'],
    'an empty gpx' => ['empty.gpx', '<gpx></gpx>'],
    'broken xml' => ['broken.gpx', '<gpx><trk>'],
]);

it('asks a guest to log in before opening a file', function (): void {
    $this->postJson(route('routes.import'), [])->assertUnauthorized();
});

it('keeps the line as it is', function (): void {
    $routingEngine = tracingEngine();
    $this->app->instance(RoutingEngine::class, $routingEngine);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.import.redo'), redoPayload('keep'))
        ->assertOk()
        ->assertJsonPath('name', 'Club loop')
        ->assertJsonCount(5, 'coordinates');

    expect($routingEngine->traced)->toBe([]);
});

it('rides the line the other way along the roads', function (): void {
    $routingEngine = tracingEngine();
    $this->app->instance(RoutingEngine::class, $routingEngine);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.import.redo'), [...redoPayload('reverse'), 'coordinates' => [[4.0, 51.0], [4.02, 51.0]]])
        ->assertOk()
        ->assertJsonPath('coordinates.0', [4.02, 51])
        ->assertJsonPath('distance_metres', 3000);

    expect($routingEngine->traced)->toHaveCount(1);
});

it('starts the loop from the pin and comes back to it', function (): void {
    $routingEngine = tracingEngine();
    $this->app->instance(RoutingEngine::class, $routingEngine);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('routes.import.redo'), redoPayload('from_start'))
        ->assertOk();

    $traced = $routingEngine->traced[0] ?? [];

    expect($traced[0] ?? null)->toBe([4.0152, 51.0102])
        ->and(array_last($traced))->toBe([4.0152, 51.0102])
        ->and($response->json('coordinates.0'))->toBe([4.0152, 51.0102]);
});

it('needs the pin to start from it', function (): void {
    $payload = redoPayload('from_start');
    unset($payload['latitude'], $payload['longitude']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.import.redo'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('says so when the roads do not join up', function (): void {
    $this->app->instance(RoutingEngine::class, tracingEngine(fails: true));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.import.redo'), redoPayload('reverse'))
        ->assertStatus(502);
});
