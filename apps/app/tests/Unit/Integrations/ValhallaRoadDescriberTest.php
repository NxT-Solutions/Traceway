<?php

declare(strict_types=1);

use App\Domain\Routing\Details\ProfilePoint;
use App\Domain\Routing\Details\RoadDescription;
use App\Domain\Routing\Details\RoadSurface;
use App\Domain\Routing\Details\SurfaceShare;
use App\Domain\Routing\Details\Turn;
use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\Sport;
use App\Integrations\Valhalla\Polyline;
use App\Integrations\Valhalla\Requests\HeightRequest;
use App\Integrations\Valhalla\Requests\TraceAttributesRequest;
use App\Integrations\Valhalla\Requests\TraceRouteRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoadDescriber;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return list<array{0: float, 1: float}>
 */
function describedLine(): array
{
    return [[4.0, 51.0], [4.002, 51.0], [4.004, 51.0], [4.006, 51.0]];
}

function traceRouteResponse(): MockResponse
{
    return MockResponse::make([
        'trip' => [
            'legs' => [[
                'shape' => Polyline::encode(describedLine()),
                'maneuvers' => [
                    ['type' => 1, 'length' => 0.14, 'begin_shape_index' => 0, 'street_names' => ['High Street']],
                    ['type' => 15, 'length' => 0.14, 'begin_shape_index' => 1, 'street_names' => ['Mill Road', 'Molenweg']],
                    ['type' => 8, 'length' => 0.1, 'begin_shape_index' => 2],
                    ['type' => 26, 'length' => 0.04, 'begin_shape_index' => 3, 'roundabout_exit_count' => 2],
                    ['type' => 4, 'length' => 0.0, 'begin_shape_index' => 3],
                ],
            ]],
        ],
    ], 200);
}

it('reads turns, surfaces, and heights for a line', function (): void {
    $mock = new MockClient([
        TraceRouteRequest::class => traceRouteResponse(),
        TraceAttributesRequest::class => MockResponse::make(['edges' => [
            ['surface' => 'paved_smooth', 'length' => 0.3],
            ['surface' => 'compacted', 'length' => 0.1],
            ['surface' => null, 'length' => 0.02],
        ]], 200),
        HeightRequest::class => MockResponse::make(['range_height' => [[0, 12], [30, 14.5], [60, null], [90, 18]]], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $roadDescription = new ValhallaRoadDescriber($connector)->describe(Sport::Gravel, describedLine());

    expect(array_map(fn (ProfilePoint $profilePoint): array => [$profilePoint->distanceMetres, $profilePoint->elevationMetres], $roadDescription->profile))
        ->toBe([[0, 12.0], [30, 14.5], [90, 18.0]])
        ->and(array_map(fn (SurfaceShare $surfaceShare): array => [$surfaceShare->surface, $surfaceShare->metres], $roadDescription->surfaces))
        ->toBe([[RoadSurface::Paved, 300], [RoadSurface::Gravel, 100], [RoadSurface::Unknown, 20]])
        ->and($roadDescription->cues)->toHaveCount(2)
        ->and($roadDescription->cues[0]->turn)->toBe(Turn::Left)
        ->and($roadDescription->cues[0]->street)->toBe('Mill Road')
        ->and($roadDescription->cues[0]->distanceMetres)->toBe(140)
        ->and($roadDescription->cues[0]->longitude)->toEqualWithDelta(4.002, 0.000001)
        ->and($roadDescription->cues[1]->turn)->toBe(Turn::Straight)
        ->and($roadDescription->cues[1]->street)->toBe('Exit 2');
});

it('asks for a cycling match on a bike and a walking one on foot', function (): void {
    $costings = [];
    $mock = new MockClient([
        TraceRouteRequest::class => function (PendingRequest $pendingRequest) use (&$costings): MockResponse {
            $costings[] = $pendingRequest->body()?->all()['costing'] ?? null;

            return traceRouteResponse();
        },
        TraceAttributesRequest::class => MockResponse::make(['edges' => []], 200),
        HeightRequest::class => MockResponse::make(['range_height' => []], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $describer = new ValhallaRoadDescriber($connector);

    $describer->describe(Sport::Mtb, describedLine());
    $describer->describe(Sport::Hike, describedLine());

    expect($costings)->toBe(['bicycle', 'pedestrian']);
});

it('reads a long line in pieces and lines their distances up', function (): void {
    $heightCalls = 0;
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make(['trip' => ['legs' => []]], 200),
        TraceAttributesRequest::class => MockResponse::make(['edges' => []], 200),
        HeightRequest::class => function () use (&$heightCalls): MockResponse {
            $heightCalls++;

            return MockResponse::make(['range_height' => [[0, 10], [100, 20]]], 200);
        },
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    // Each hop is about 140 m, so a 200 m limit gives three pieces.
    $roadDescription = new ValhallaRoadDescriber($connector, maxPieceMetres: 200, maxGapMetres: 1000)->describe(Sport::Road, describedLine());
    $distances = array_map(fn (ProfilePoint $profilePoint): int => $profilePoint->distanceMetres, $roadDescription->profile);

    expect($heightCalls)->toBe(3)
        ->and($distances)->toHaveCount(4)
        ->and($distances[0])->toBe(0)
        ->and($distances)->toBe(array_values(array_unique($distances)))
        ->and($distances[3])->toBeGreaterThan(300);
});

it('fills a gap the router would break on', function (): void {
    $points = 0;
    $mock = new MockClient([
        TraceRouteRequest::class => function (PendingRequest $pendingRequest) use (&$points): MockResponse {
            $shape = $pendingRequest->body()?->all()['encoded_polyline'] ?? '';
            $points = count(Polyline::decode(is_string($shape) ? $shape : ''));

            return MockResponse::make(['trip' => ['legs' => []]], 200);
        },
        TraceAttributesRequest::class => MockResponse::make(['edges' => []], 200),
        HeightRequest::class => MockResponse::make(['range_height' => []], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    // One hop of about 1.4 km becomes hops under 400 m.
    new ValhallaRoadDescriber($connector)->describe(Sport::Road, [[4.0, 51.0], [4.02, 51.0]]);

    expect($points)->toBe(5);
});

it('keeps the heights when the turns do not come back', function (): void {
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make(['error' => 'No suitable edges'], 400),
        TraceAttributesRequest::class => MockResponse::make(['error' => 'No suitable edges'], 400),
        HeightRequest::class => MockResponse::make(['range_height' => [[0, 5], [30, 6]]], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $roadDescription = new ValhallaRoadDescriber($connector)->describe(Sport::Run, describedLine());

    expect($roadDescription->profile)->toHaveCount(2)
        ->and($roadDescription->cues)->toBe([])
        ->and($roadDescription->surfaces[0]->surface)->toBe(RoadSurface::Unknown)
        ->and($roadDescription->complete)->toBeFalse();
});

it('keeps the turns and surfaces when the heights come back as a page', function (string $body): void {
    $mock = new MockClient([
        TraceRouteRequest::class => traceRouteResponse(),
        TraceAttributesRequest::class => MockResponse::make(['edges' => [['surface' => 'paved', 'length' => 0.42]]], 200),
        HeightRequest::class => MockResponse::make($body, 200, ['Content-Type' => 'text/html']),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $roadDescription = new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Road, describedLine());

    expect($roadDescription->profile)->toBe([])
        ->and($roadDescription->cues)->not->toBe([])
        ->and($roadDescription->surfaces[0]->surface)->toBe(RoadSurface::Paved)
        ->and($roadDescription->complete)->toBeFalse();
})->with([
    'a page' => '<html><body>Please wait</body></html>',
    'a bare string' => '"Please wait"',
    'nothing' => '',
]);

it('fails when the server answers nothing at all', function (): void {
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make([], 500),
        TraceAttributesRequest::class => MockResponse::make([], 500),
        HeightRequest::class => MockResponse::make([], 500),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    expect(fn (): RoadDescription => new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Run, describedLine()))
        ->toThrow(MapServerBusy::class, 'The router could not describe that line (route 500, surface 500, height 500).');
});

it('says the map server is busy when it cannot be reached', function (): void {
    $timeout = MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Connection timed out'), $pendingRequest));
    $mock = new MockClient([
        TraceRouteRequest::class => $timeout,
        TraceAttributesRequest::class => $timeout,
        HeightRequest::class => $timeout,
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    expect(fn (): RoadDescription => new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Run, describedLine()))
        ->toThrow(MapServerBusy::class, 'The router could not describe that line (route unreachable, surface unreachable, height unreachable).');
    $mock->assertSentCount(6);
});

it('fails, and is not busy, when the server turns the line down', function (): void {
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make(['error_code' => 171, 'error' => 'No suitable edges near location'], 400),
        TraceAttributesRequest::class => MockResponse::make(['error_code' => 171, 'error' => 'No suitable edges near location'], 400),
        HeightRequest::class => MockResponse::make(['error_code' => 154, 'error' => 'Path distance exceeds the max distance limit'], 400),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    expect(fn (): RoadDescription => new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Run, describedLine()))
        ->toThrow(fn (RuntimeException $runtimeException): bool => ! $runtimeException instanceof MapServerBusy
            && $runtimeException->getMessage() === 'The router could not describe that line (route 400/171, surface 400/171, height 400/154).');
});

it('asks once more for what a busy server turned away', function (): void {
    $mock = new MockClient([
        MockResponse::make(['error_code' => 429, 'error' => 'Too many requests'], 429),
        MockResponse::make(['error_code' => 429, 'error' => 'Too many requests'], 429),
        MockResponse::make(['error_code' => 429, 'error' => 'Too many requests'], 429),
        traceRouteResponse(),
        MockResponse::make(['edges' => [['surface' => 'paved', 'length' => 0.42]]], 200),
        MockResponse::make(['range_height' => [[0, 5], [30, 6]]], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $roadDescription = new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Road, describedLine());

    expect($roadDescription->complete)->toBeTrue()
        ->and($roadDescription->profile)->toHaveCount(2);
    $mock->assertSentCount(6);
});

it('says why, when nothing came back even the second time', function (): void {
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make(['error_code' => 429, 'error' => 'Too many requests'], 429),
        TraceAttributesRequest::class => MockResponse::make(['error_code' => 429, 'error' => 'Too many requests'], 429),
        HeightRequest::class => MockResponse::make([], 503),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    expect(fn (): RoadDescription => new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Road, describedLine()))
        ->toThrow(RuntimeException::class, 'The router could not describe that line (route 429/429, surface 429/429, height 503).');
    $mock->assertSentCount(6);
});

it('asks once more when a proxy answers for the busy server with a page', function (): void {
    $page = '<html><head><title>502 Bad Gateway</title></head><body>nginx</body></html>';
    $mock = new MockClient([
        MockResponse::make($page, 502, ['Content-Type' => 'text/html']),
        MockResponse::make($page, 502, ['Content-Type' => 'text/html']),
        MockResponse::make($page, 502, ['Content-Type' => 'text/html']),
        traceRouteResponse(),
        MockResponse::make(['edges' => [['surface' => 'paved', 'length' => 0.42]]], 200),
        MockResponse::make(['range_height' => [[0, 5], [30, 6]]], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $roadDescription = new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Road, describedLine());

    expect($roadDescription->complete)->toBeTrue();
    $mock->assertSentCount(6);
});

it('says the status when a page came back instead of an error', function (): void {
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make('<html>Bad Gateway</html>', 502, ['Content-Type' => 'text/html']),
        TraceAttributesRequest::class => MockResponse::make('<html>Bad Gateway</html>', 502, ['Content-Type' => 'text/html']),
        HeightRequest::class => MockResponse::make('"Gateway Timeout"', 504, ['Content-Type' => 'application/json']),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    expect(fn (): RoadDescription => new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Road, describedLine()))
        ->toThrow(RuntimeException::class, 'The router could not describe that line (route 502, surface 502, height 504).');
});

it('does not ask again for a line the roads do not match', function (): void {
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make(['error_code' => 171, 'error' => 'No suitable edges near location'], 400),
        TraceAttributesRequest::class => MockResponse::make(['error_code' => 171, 'error' => 'No suitable edges near location'], 400),
        HeightRequest::class => MockResponse::make(['range_height' => [[0, 5], [30, 6]]], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    new ValhallaRoadDescriber($connector, retryPauseMilliseconds: 0)->describe(Sport::Road, describedLine());

    $mock->assertSentCount(3);
});
