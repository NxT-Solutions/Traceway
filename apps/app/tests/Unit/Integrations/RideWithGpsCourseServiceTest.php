<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Routing\Sport;
use App\Integrations\RideWithGps\Requests\DeleteRouteRequest;
use App\Integrations\RideWithGps\Requests\GetAccessTokenRequest;
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
    $connector = new RideWithGpsConnector('client', 'secret', 'https://traceway.test/settings/services/ride_with_gps/callback', baseUrl: 'https://rwgps.test');
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

it('trades the sign-in code for a token that does not run out', function (): void {
    $mock = new MockClient([
        GetAccessTokenRequest::class => MockResponse::make(['access_token' => 'access', 'token_type' => 'bearer']),
    ]);

    $serviceTokens = rideWithGpsService($mock)->connect('the-code');

    expect($serviceTokens->accessToken)->toBe('access')
        ->and($serviceTokens->refreshToken)->toBeNull()
        ->and($serviceTokens->expiresAt)->toBeNull();
    $mock->assertSent(fn (GetAccessTokenRequest $getAccessTokenRequest, Response $response): bool => $response->getPendingRequest()->body()?->all() === [
        'grant_type' => 'authorization_code',
        'code' => 'the-code',
        'client_id' => 'client',
        'client_secret' => 'secret',
        'redirect_uri' => 'https://traceway.test/settings/services/ride_with_gps/callback',
    ] && $response->getPendingRequest()->getUrl() === 'https://rwgps.test/oauth/token.json');
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

    expect($publishedCourse->processing())->toBeTrue();
});

it('replaces the route it sent before, even when the rider already deleted it', function (int $status): void {
    $mock = new MockClient([
        MockResponse::make([], $status),
        rideWithGpsTask('completed', [['item_type' => 'route', 'item_id' => 322]]),
    ]);

    $publishedCourse = rideWithGpsService($mock)->publish(new ServiceTokens('access'), rideWithGpsUpload(), '321');

    expect($publishedCourse->id)->toBe('322');
    $mock->assertSent(fn (DeleteRouteRequest $deleteRouteRequest, Response $response): bool => str_ends_with($response->getPendingRequest()->getUrl(), '/api/v1/routes/321.json'));
})->with([
    'deleted now' => 204,
    'already gone' => 404,
]);

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
