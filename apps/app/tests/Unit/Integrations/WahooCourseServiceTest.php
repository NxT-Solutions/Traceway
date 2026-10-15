<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Domain\Routing\Sport;
use App\Integrations\Wahoo\Requests\CreateRouteRequest;
use App\Integrations\Wahoo\Requests\FindRoutesRequest;
use App\Integrations\Wahoo\Requests\RevokePermissionsRequest;
use App\Integrations\Wahoo\Requests\UpdateRouteRequest;
use App\Integrations\Wahoo\WahooConnector;
use App\Integrations\Wahoo\WahooCourseService;
use Illuminate\Support\Facades\Date;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetAccessTokenRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

function wahooService(MockClient $mockClient): WahooCourseService
{
    $connector = new WahooConnector('client', 'secret', 'https://traceway.test/settings/services/wahoo/callback', 'https://wahoo.test');
    $connector->withMockClient($mockClient);

    return new WahooCourseService($connector);
}

function wahooUpload(Sport $sport = Sport::Road): CourseUpload
{
    return new CourseUpload(
        'Morning loop to the coast', 'Morning loop', $sport, 'FIT-BYTES', '<gpx/>',
        42_000, 380, 51.0907, 4.9166, '0199c0de-0000-7000-8000-000000000001', '2026-10-15T08:00:00+00:00',
    );
}

/**
 * @return array<array-key, mixed>
 */
function wahooBody(Response $response): array
{
    return $response->getPendingRequest()->body()?->all() ?? [];
}

it('trades the sign-in code for tokens that run out', function (): void {
    $mock = new MockClient([
        GetAccessTokenRequest::class => MockResponse::make(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 7200]),
    ]);

    $serviceTokens = wahooService($mock)->connect('the-code');

    expect($serviceTokens->accessToken)->toBe('access')
        ->and($serviceTokens->refreshToken)->toBe('refresh')
        ->and($serviceTokens->expiresAt)->toBeGreaterThan(Date::now()->getTimestamp() + 7000);
    $mock->assertSent(fn (Request $request, Response $response): bool => wahooBody($response)['code'] === 'the-code'
        && wahooBody($response)['grant_type'] === 'authorization_code'
        && $response->getPendingRequest()->getUrl() === 'https://wahoo.test/oauth/token');
});

it('reports a sign-in Wahoo turned down', function (): void {
    wahooService(new MockClient([
        GetAccessTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
    ]))->connect('old-code');
})->throws(ServiceRefused::class);

it('adds a new route to the library as a FIT file with the sport Wahoo knows', function (Sport $sport, int $family): void {
    $mock = new MockClient([
        CreateRouteRequest::class => MockResponse::make(['id' => 4711], 201),
    ]);

    $publishedCourse = wahooService($mock)->publish(new ServiceTokens('access'), wahooUpload($sport), previousId: null);

    expect($publishedCourse->id)->toBe('4711')->and($publishedCourse->processing())->toBeFalse();
    $mock->assertSent(function (CreateRouteRequest $createRouteRequest, Response $response) use ($family): bool {
        $body = wahooBody($response);

        return $body['route[file]'] === 'data:application/vnd.fit;base64,'.base64_encode('FIT-BYTES')
            && $body['route[external_id]'] === '0199c0de-0000-7000-8000-000000000001'
            && $body['route[name]'] === 'Morning loop to the coast'
            && $body['route[filename]'] === 'morning-loop-to-the-coast.fit'
            && $body['route[workout_type_family_id]'] === $family
            && $body['route[distance]'] === 42_000
            && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer access';
    });
})->with([
    'gravel' => [Sport::Gravel, 0],
    'trail run' => [Sport::TrailRun, 1],
    'hike' => [Sport::Hike, 9],
]);

it('updates the route it sent before', function (): void {
    $mock = new MockClient([
        UpdateRouteRequest::class => MockResponse::make(['id' => 4711]),
    ]);

    $publishedCourse = wahooService($mock)->publish(new ServiceTokens('access'), wahooUpload(), '4711');

    expect($publishedCourse->id)->toBe('4711');
    $mock->assertSent(fn (UpdateRouteRequest $updateRouteRequest, Response $response): bool => str_ends_with($response->getPendingRequest()->getUrl(), '/v1/routes/4711'));
    $mock->assertNotSent(CreateRouteRequest::class);
});

it('adds the route again when the rider deleted it in Wahoo', function (): void {
    $mock = new MockClient([
        UpdateRouteRequest::class => MockResponse::make([], 404),
        CreateRouteRequest::class => MockResponse::make(['id' => 5000], 201),
    ]);

    expect(wahooService($mock)->publish(new ServiceTokens('access'), wahooUpload(), '4711')->id)->toBe('5000');
});

it('updates a copy Wahoo already holds for the route instead of adding a second one', function (): void {
    $mock = new MockClient([
        CreateRouteRequest::class => MockResponse::make(['errors' => ['external_id' => ['taken']]], 422),
        FindRoutesRequest::class => MockResponse::make([
            ['id' => 12, 'external_id' => 'someone-else'],
            ['id' => 4711, 'external_id' => '0199c0de-0000-7000-8000-000000000001'],
        ]),
        UpdateRouteRequest::class => MockResponse::make(['id' => 4711]),
    ]);

    expect(wahooService($mock)->publish(new ServiceTokens('access'), wahooUpload(), previousId: null)->id)->toBe('4711');
    $mock->assertSent(fn (UpdateRouteRequest $updateRouteRequest, Response $response): bool => str_ends_with($response->getPendingRequest()->getUrl(), '/v1/routes/4711'));
});

it('asks to connect again when Wahoo no longer accepts the token', function (): void {
    wahooService(new MockClient([
        CreateRouteRequest::class => MockResponse::make([], 401),
    ]))->publish(new ServiceTokens('access'), wahooUpload(), previousId: null);
})->throws(ServiceRefused::class);

it('says Wahoo is unavailable when it fails', function (): void {
    wahooService(new MockClient([
        CreateRouteRequest::class => MockResponse::make([], 503),
    ]))->publish(new ServiceTokens('access'), wahooUpload(), previousId: null);
})->throws(ServiceUnavailable::class);

it('withdraws its access when the rider disconnects', function (): void {
    $mock = new MockClient([
        RevokePermissionsRequest::class => MockResponse::make([], 204),
    ]);

    wahooService($mock)->revoke(new ServiceTokens('access'));

    $mock->assertSent(RevokePermissionsRequest::class);
});
