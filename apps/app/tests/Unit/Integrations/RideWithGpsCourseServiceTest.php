<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\PublishedCourse;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Domain\Routing\Sport;
use App\Integrations\RideWithGps\Requests\DeleteRouteRequest;
use App\Integrations\RideWithGps\Requests\GetCurrentUserRequest;
use App\Integrations\RideWithGps\Requests\GetTaskRequest;
use App\Integrations\RideWithGps\Requests\RevokeTokenRequest;
use App\Integrations\RideWithGps\Requests\UploadRouteRequest;
use App\Integrations\RideWithGps\RideWithGpsConnector;
use App\Integrations\RideWithGps\RideWithGpsCourseService;
use Saloon\Data\MultipartValue;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

function rideWithGpsService(MockClient $mockClient, int $attempts = 3): RideWithGpsCourseService
{
    $connector = new RideWithGpsConnector('client', 'secret', baseUrl: 'https://rwgps.test');
    $connector->withMockClient($mockClient);

    return new RideWithGpsCourseService($connector, $attempts, 0);
}

function rideWithGpsUpload(): CourseUpload
{
    return new CourseUpload(
        'Gravel to the abbey', 'Gravel abbey', Sport::Gravel, 'FIT-BYTES', '<gpx>abbey</gpx>',
        64_000, 410, 51.0, 4.9, '0199c0de-0000-7000-8000-000000000002', '2026-10-15T08:00:00+00:00',
    );
}

/**
 * @param  list<array{item_type: string, item_id: int}>  $items
 */
function rideWithGpsTask(string $status, array $items = []): MockResponse
{
    return MockResponse::make(['task' => ['id' => 99, 'status' => $status, 'items' => $items, 'errors' => []]], 202);
}

it('keeps its key, which does not run out', function (): void {
    $serviceTokens = new ServiceTokens('access');

    expect(rideWithGpsService(new MockClient([]))->refresh($serviceTokens))->toBe($serviceTokens);
});

it('sends the API key with each request when one is set', function (): void {
    $connector = new RideWithGpsConnector('client', 'secret', 'the-api-key', 'https://rwgps.test');
    $mock = new MockClient([
        UploadRouteRequest::class => rideWithGpsTask('completed', [['item_type' => 'route', 'item_id' => 321]]),
    ]);
    $connector->withMockClient($mock);

    new RideWithGpsCourseService($connector, 1, 0)->publish(new ServiceTokens('access'), rideWithGpsUpload(), previousId: null);

    $mock->assertSent(fn (UploadRouteRequest $uploadRouteRequest, Response $response): bool => $response->getPendingRequest()->headers()->get('x-rwgps-api-key') === 'the-api-key');
});

it('uploads the route as GPX and waits until Ride with GPS has read it', function (): void {
    $mock = new MockClient([
        rideWithGpsTask('pending'),
        rideWithGpsTask('pending'),
        rideWithGpsTask('completed', [['item_type' => 'route', 'item_id' => 321]]),
    ]);

    $publishedCourse = rideWithGpsService($mock)->publish(new ServiceTokens('access'), rideWithGpsUpload(), previousId: null);

    expect($publishedCourse->id)->toBe('321')
        ->and($publishedCourse->url)->toBe('https://rwgps.test/routes/321');
    $mock->assertSent(function (UploadRouteRequest $uploadRouteRequest, Response $response): bool {
        $file = $response->getPendingRequest()->body()?->get('file');

        return $file instanceof MultipartValue
            && $file->value === '<gpx>abbey</gpx>'
            && $file->filename === 'gravel-to-the-abbey.gpx'
            && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer access';
    });
    $mock->assertSentCount(2, GetTaskRequest::class);
});

it('reports a slow upload as still being read', function (): void {
    $mock = new MockClient([
        rideWithGpsTask('pending'),
        rideWithGpsTask('pending'),
        rideWithGpsTask('pending'),
    ]);

    $publishedCourse = rideWithGpsService($mock, attempts: 2)->publish(new ServiceTokens('access'), rideWithGpsUpload(), previousId: null);

    expect($publishedCourse->processing)->toBeTrue()->and($publishedCourse->id)->toBe('task:99');
});

it('replaces the route it sent before once the new copy is in, even when the rider already deleted it', function (int $status): void {
    $mock = new MockClient([
        UploadRouteRequest::class => rideWithGpsTask('completed', [['item_type' => 'route', 'item_id' => 322]]),
        DeleteRouteRequest::class => MockResponse::make([], $status),
    ]);

    $publishedCourse = rideWithGpsService($mock)->publish(new ServiceTokens('access'), rideWithGpsUpload(), '321');

    expect($publishedCourse->id)->toBe('322');
    $mock->assertSent(fn (DeleteRouteRequest $deleteRouteRequest, Response $response): bool => str_ends_with($response->getPendingRequest()->getUrl(), '/api/v1/routes/321.json'));
})->with([
    'deleted now' => 204,
    'already gone' => 404,
    'delete failed' => 500,
]);

it('keeps the route it sent before when the new upload fails', function (): void {
    $mock = new MockClient([
        UploadRouteRequest::class => MockResponse::make([], 503),
    ]);

    expect(fn (): PublishedCourse => rideWithGpsService($mock)->publish(new ServiceTokens('access'), rideWithGpsUpload(), '321'))
        ->toThrow(ServiceUnavailable::class);
    $mock->assertNotSent(DeleteRouteRequest::class);
});

it('keeps a slow upload by its task, and replaces the route it became on the next send', function (): void {
    $slow = new MockClient([
        UploadRouteRequest::class => rideWithGpsTask('pending'),
        GetTaskRequest::class => rideWithGpsTask('pending'),
    ]);

    $publishedCourse = rideWithGpsService($slow, attempts: 1)->publish(new ServiceTokens('access'), rideWithGpsUpload(), previousId: null);

    expect($publishedCourse->id)->toBe('task:99')->and($publishedCourse->processing)->toBeTrue();

    $next = new MockClient([
        GetTaskRequest::class => rideWithGpsTask('completed', [['item_type' => 'route', 'item_id' => 321]]),
        UploadRouteRequest::class => rideWithGpsTask('completed', [['item_type' => 'route', 'item_id' => 322]]),
        DeleteRouteRequest::class => MockResponse::make([], 204),
    ]);

    expect(rideWithGpsService($next)->publish(new ServiceTokens('access'), rideWithGpsUpload(), 'task:99')->id)->toBe('322');
    $next->assertSent(fn (DeleteRouteRequest $deleteRouteRequest, Response $response): bool => str_ends_with($response->getPendingRequest()->getUrl(), '/api/v1/routes/321.json'));
});

it('asks to connect again when Ride with GPS no longer accepts the token', function (): void {
    rideWithGpsService(new MockClient([
        UploadRouteRequest::class => MockResponse::make([], 401),
    ]))->publish(new ServiceTokens('access'), rideWithGpsUpload(), previousId: null);
})->throws(ServiceRefused::class);

it('withdraws the token when the rider disconnects', function (): void {
    $mock = new MockClient([
        RevokeTokenRequest::class => MockResponse::make([]),
    ]);

    rideWithGpsService($mock)->revoke(new ServiceTokens('access'));

    $mock->assertSent(fn (RevokeTokenRequest $revokeTokenRequest, Response $response): bool => $response->getPendingRequest()->body()?->all() === [
        'client_id' => 'client',
        'client_secret' => 'secret',
        'token' => 'access',
    ]);
});

it('reads which Ride with GPS account its key belongs to, with the API key when one is set', function (): void {
    $connector = new RideWithGpsConnector('client', 'secret', 'the-api-key', 'https://rwgps.test');
    $mock = new MockClient([
        GetCurrentUserRequest::class => MockResponse::make(['user' => ['id' => 4711, 'name' => 'Road Rider', 'email' => 'road@rwgps.test']]),
    ]);
    $connector->withMockClient($mock);

    $serviceAccount = new RideWithGpsCourseService($connector, 1, 0)->account(new ServiceTokens('access'));

    expect($serviceAccount->id)->toBe('4711')
        ->and($serviceAccount->email)->toBe('road@rwgps.test');
    $mock->assertSent(fn (GetCurrentUserRequest $getCurrentUserRequest, Response $response): bool => $response->getPendingRequest()->getUrl() === 'https://rwgps.test/api/v1/users/current.json'
        && $response->getPendingRequest()->getMethod()->value === 'GET'
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer access'
        && $response->getPendingRequest()->headers()->get('x-rwgps-api-key') === 'the-api-key');
});

it('reads a Ride with GPS account that shares no email', function (): void {
    $serviceAccount = rideWithGpsService(new MockClient([
        GetCurrentUserRequest::class => MockResponse::make(['user' => ['id' => '4711', 'email' => '']]),
    ]))->account(new ServiceTokens('access'));

    expect($serviceAccount->id)->toBe('4711')
        ->and($serviceAccount->email)->toBeNull();
});

it('asks to connect again when Ride with GPS no longer accepts the key it asks with', function (): void {
    rideWithGpsService(new MockClient([
        GetCurrentUserRequest::class => MockResponse::make([], 401),
    ]))->account(new ServiceTokens('access'));
})->throws(ServiceRefused::class);

it('says Ride with GPS is unavailable when it does not say whose account it is', function (MockResponse $mockResponse): void {
    rideWithGpsService(new MockClient([
        GetCurrentUserRequest::class => $mockResponse,
    ]))->account(new ServiceTokens('access'));
})->with([
    'a failure' => fn (): MockResponse => MockResponse::make([], 500),
    'no account' => fn (): MockResponse => MockResponse::make(['id' => 4711]),
    'an account without a number' => fn (): MockResponse => MockResponse::make(['user' => ['email' => 'road@rwgps.test']]),
    'a blank number' => fn (): MockResponse => MockResponse::make(['user' => ['id' => '']]),
])->throws(ServiceUnavailable::class);

it('ends the key a new log in replaced, since its keys never run out', function (): void {
    $mock = new MockClient([
        RevokeTokenRequest::class => MockResponse::make([]),
    ]);

    rideWithGpsService($mock)->supersede(new ServiceTokens('access-old'));

    $mock->assertSentCount(1);
    $mock->assertSent(fn (RevokeTokenRequest $revokeTokenRequest, Response $response): bool => $response->getPendingRequest()->getUrl() === 'https://rwgps.test/oauth/revoke.json'
        && $response->getPendingRequest()->body()?->all() === [
            'client_id' => 'client',
            'client_secret' => 'secret',
            'token' => 'access-old',
        ]);
});
