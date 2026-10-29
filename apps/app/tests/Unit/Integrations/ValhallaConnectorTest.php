<?php

declare(strict_types=1);

use App\Domain\Routing\MapServerBusy;
use App\Integrations\Valhalla\Requests\LocateRequest;
use App\Integrations\Valhalla\Requests\RouteRequest;
use App\Integrations\Valhalla\Requests\TraceRouteRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Routes\CachedMapServerPace;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Saloon\Config;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Config::sleepUsing(sleepHandler: null);
});

/**
 * A connector that paces itself, with one minute's share of $perMinute, and
 * answers each request in turn with the next response.
 *
 * @return array{0: ValhallaConnector, 1: MockClient}
 */
function pacedValhalla(int $perMinute, MockResponse ...$mockResponses): array
{
    $mock = new MockClient(array_values($mockResponses));
    $connector = new ValhallaConnector('https://valhalla.test', new CachedMapServerPace(new Repository(new ArrayStore), $perMinute));
    $connector->withMockClient($mock);

    return [$connector, $mock];
}

function unreached(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Connection refused'), $pendingRequest));
}

/**
 * @param  list<RouteRequest>  $requests
 */
function sendAll(ValhallaConnector $valhallaConnector, array $requests): void
{
    $valhallaConnector->pool(requests: $requests, concurrency: 3)->send()->wait();
}

it('rests when a batch gets no answer, and asks nothing more while it rests', function (): void {
    [$connector, $mock] = pacedValhalla(60, unreached(), unreached(), unreached(), MockResponse::make([], 200));

    sendAll($connector, [new RouteRequest([]), new RouteRequest([]), new RouteRequest([])]);

    expect(fn () => $connector->send(new RouteRequest([])))->toThrow(MapServerBusy::class);
    $mock->assertSentCount(3);
});

it('rests at once when the server turns a request away as busy', function (): void {
    [$connector, $mock] = pacedValhalla(60, MockResponse::make([], 200), MockResponse::make([], 429), MockResponse::make([], 200), MockResponse::make([], 200));

    sendAll($connector, [new RouteRequest([]), new RouteRequest([]), new RouteRequest([])]);

    expect(fn () => $connector->send(new RouteRequest([])))->toThrow(MapServerBusy::class);
    $mock->assertSentCount(3);
});

it('keeps asking when the server answers, even with a no', function (): void {
    [$connector, $mock] = pacedValhalla(60, MockResponse::make(['error_code' => 442], 400), MockResponse::make(['error_code' => 442], 400), MockResponse::make([], 200));

    sendAll($connector, [new RouteRequest([]), new RouteRequest([])]);
    $connector->send(new RouteRequest([]));

    $mock->assertSentCount(3);
});

it('does not rest for one slow or failed request on its own', function (): void {
    [$connector, $mock] = pacedValhalla(60, MockResponse::make([], 503), MockResponse::make([], 200), MockResponse::make([], 200));

    expect(fn () => $connector->send(new TraceRouteRequest([])))->not->toThrow(MapServerBusy::class);

    sendAll($connector, [new RouteRequest([])]);
    $connector->send(new RouteRequest([]));

    $mock->assertSentCount(3);
});

it('rests, and does not ask again, when the server turns a lookup away as busy', function (): void {
    Config::sleepUsing(static function (): void {});
    $asked = 0;
    [$connector] = pacedValhalla(60, MockResponse::make([], 429), MockResponse::make([], 200));
    $connector->middleware()->onRequest(function () use (&$asked): void {
        $asked++;
    });

    expect($connector->send(new LocateRequest(51.05, 3.72, 'bicycle', []))->status())->toBe(429)
        ->and($asked)->toBe(1)
        ->and(fn () => $connector->send(new RouteRequest([])))->toThrow(MapServerBusy::class);
});

it('counts a lookup asked again against the share', function (): void {
    Config::sleepUsing(static function (): void {});
    [$connector, $mock] = pacedValhalla(1, MockResponse::make([], 503), MockResponse::make([], 200));

    expect(fn () => $connector->send(new LocateRequest(51.05, 3.72, 'bicycle', [])))->toThrow(MapServerBusy::class);
    $mock->assertSentCount(1);
});

it('hands every answer to the handlers it was given', function (): void {
    [$connector] = pacedValhalla(60, MockResponse::make([], 200), MockResponse::make([], 400));
    $answers = [];

    $connector->pool(
        requests: ['a' => new RouteRequest([]), 'b' => new RouteRequest([])],
        responseHandler: function (Response $response, int|string $key) use (&$answers): void {
            $answers[$key] = $response->status();
        },
        exceptionHandler: function (mixed $reason, int|string $key) use (&$answers): void {
            $answers[$key] = $reason instanceof RequestException ? $reason->getStatus() : 0;
        },
    )->send()->wait();

    ksort($answers);

    expect($answers)->toBe(['a' => 200, 'b' => 400]);
});

it('sends none of a batch that does not fit in this minute', function (): void {
    [$connector, $mock] = pacedValhalla(2, MockResponse::make([], 200), MockResponse::make([], 200), MockResponse::make([], 200));

    expect(fn () => sendAll($connector, [new RouteRequest([]), new RouteRequest([]), new RouteRequest([])]))
        ->toThrow(MapServerBusy::class, 'Traceway asked the map server for enough this minute.');
    $mock->assertNothingSent();
});

it('rests when a route asked on its own gets no answer, so the backup draws at once', function (): void {
    [$connector, $mock] = pacedValhalla(60, unreached(), MockResponse::make([], 200));

    sendAll($connector, [new RouteRequest([])]);

    expect(fn () => $connector->send(new RouteRequest([])))->toThrow(MapServerBusy::class);
    $mock->assertSentCount(1);
});
