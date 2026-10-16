<?php

declare(strict_types=1);

use App\Domain\Status\ServiceState;
use App\Domain\Status\StatusComponent;
use App\Integrations\Status\ConnectIqStoreProbe;
use App\Integrations\Status\HttpServiceProbe;
use App\Integrations\Status\Requests\PingRequest;
use App\Integrations\Status\StatusConnector;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\TestCase;

uses(TestCase::class);

function statusConnector(MockResponse $mockResponse, array &$asked = []): StatusConnector
{
    $connector = new StatusConnector('https://service.test/');
    $connector->withMockClient(new MockClient([
        PingRequest::class => function (PendingRequest $pendingRequest) use ($mockResponse, &$asked): MockResponse {
            $asked[] = $pendingRequest->getUrl();

            return $mockResponse;
        },
    ]));

    return $connector;
}

it('finds a service up when its page answers', function (): void {
    $asked = [];
    $probeResult = new HttpServiceProbe(StatusComponent::Routing, statusConnector(MockResponse::make('{"version":"3.5"}'), $asked), '/status')->check();

    expect($probeResult->state)->toBe(ServiceState::Up)
        ->and($probeResult->milliseconds)->toBeGreaterThanOrEqual(0)
        ->and($asked)->toBe(['https://service.test/status']);
});

it('finds a service down when it fails or cannot be reached', function (MockResponse $mockResponse): void {
    expect(new HttpServiceProbe(StatusComponent::Places, statusConnector($mockResponse))->check()->state)->toBe(ServiceState::Down);
})->with([
    'a server error' => fn (): MockResponse => MockResponse::make('busy', 503),
    'a timeout' => fn (): MockResponse => MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Timed out'), $pendingRequest)),
]);

it('tells the store listing apart from a store that is down', function (MockResponse $mockResponse, ServiceState $serviceState): void {
    $asked = [];
    $probeResult = new ConnectIqStoreProbe(statusConnector($mockResponse, $asked), 'a0c32e89-323f-4522-9ee8-c6af6cce14c3')->check();

    expect($probeResult->state)->toBe($serviceState)
        ->and($asked)->toBe(['https://service.test/api/appsLibraryExternalServices/api/asw/apps/a0c32e89-323f-4522-9ee8-c6af6cce14c3']);
})->with([
    'approved' => fn (): array => [MockResponse::make(['id' => 'a0c32e89-323f-4522-9ee8-c6af6cce14c3', 'appLocalizations' => []]), ServiceState::Up],
    'not approved yet' => fn (): array => [MockResponse::make(['className' => 'ResourceNotFoundException', 'message' => 'The requested app is not approved. appId=a0c32e89'], 404), ServiceState::Waiting],
    'another app' => fn (): array => [MockResponse::make(['id' => 'someone-else']), ServiceState::Down],
    'the store fails' => fn (): array => [MockResponse::make('<html>', 502), ServiceState::Down],
]);
