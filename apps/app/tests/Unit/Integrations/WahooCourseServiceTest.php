<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Domain\Routing\Sport;
use App\Integrations\Wahoo\Requests\CreateRouteRequest;
use App\Integrations\Wahoo\Requests\FindRoutesRequest;
use App\Integrations\Wahoo\Requests\GetUserRequest;
use App\Integrations\Wahoo\Requests\RevokePermissionsRequest;
use App\Integrations\Wahoo\Requests\UpdateRouteRequest;
use App\Integrations\Wahoo\WahooConnector;
use App\Integrations\Wahoo\WahooCourseService;
use Illuminate\Support\Facades\Date;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetRefreshTokenRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

function wahooService(MockClient $mockClient): WahooCourseService
{
    $connector = new WahooConnector('client', 'secret', 'https://wahoo.test');
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

it('refreshes keys that ran out, with the time the new ones run out', function (): void {
    $mock = new MockClient([
        GetRefreshTokenRequest::class => MockResponse::make(['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 7200]),
    ]);

    $serviceTokens = wahooService($mock)->refresh(new ServiceTokens('access', 'refresh', Date::now()->getTimestamp() - 60));

    expect($serviceTokens->accessToken)->toBe('access-2')
        ->and($serviceTokens->refreshToken)->toBe('refresh-2')
        ->and($serviceTokens->expiresAt)->toBeGreaterThan(Date::now()->getTimestamp() + 7000);
    $mock->assertSent(fn (Request $request, Response $response): bool => wahooBody($response)['refresh_token'] === 'refresh'
        && wahooBody($response)['grant_type'] === 'refresh_token'
        && wahooBody($response)['client_id'] === 'client'
        && wahooBody($response)['client_secret'] === 'secret'
        && $response->getPendingRequest()->getUrl() === 'https://wahoo.test/oauth/token');
});

it('asks to connect again when Wahoo will not refresh the keys', function (?string $refreshToken, int $status): void {
    wahooService(new MockClient([
        GetRefreshTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], $status),
    ]))->refresh(new ServiceTokens('access', $refreshToken));
})->with([
    'no refresh token' => [null, 200],
    'turned down' => ['refresh', 400],
])->throws(ServiceRefused::class);

it('says Wahoo is unavailable, not that the keys are dead, when its refresh fails on its side or is too busy', function (int $status): void {
    wahooService(new MockClient([
        GetRefreshTokenRequest::class => MockResponse::make(['error' => 'server_error'], $status),
    ]))->refresh(new ServiceTokens('access', 'refresh'));
})->with([
    'a server error' => [500],
    'down for upkeep' => [503],
    'too many requests' => [429],
])->throws(ServiceUnavailable::class);

it('adds a new route to the library as a FIT file with the sport Wahoo knows', function (Sport $sport, int $family): void {
    $mock = new MockClient([
        CreateRouteRequest::class => MockResponse::make(['id' => 4711], 201),
    ]);

    $publishedCourse = wahooService($mock)->publish(new ServiceTokens('access'), wahooUpload($sport), previousId: null);

    expect($publishedCourse->id)->toBe('4711')->and($publishedCourse->processing)->toBeFalse();
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

it('reads which Wahoo account its keys belong to', function (): void {
    $mock = new MockClient([
        GetUserRequest::class => MockResponse::make(['id' => 60462, 'first' => 'Road', 'last' => 'Rider', 'email' => 'road@wahoo.test']),
    ]);

    $serviceAccount = wahooService($mock)->account(new ServiceTokens('access'));

    expect($serviceAccount->id)->toBe('60462')
        ->and($serviceAccount->email)->toBe('road@wahoo.test');
    $mock->assertSent(fn (GetUserRequest $getUserRequest, Response $response): bool => $response->getPendingRequest()->getUrl() === 'https://wahoo.test/v1/user'
        && $response->getPendingRequest()->getMethod()->value === 'GET'
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer access');
});

it('reads a Wahoo account that shares no email', function (array $user): void {
    $serviceAccount = wahooService(new MockClient([
        GetUserRequest::class => MockResponse::make($user),
    ]))->account(new ServiceTokens('access'));

    expect($serviceAccount->id)->toBe('60462')
        ->and($serviceAccount->email)->toBeNull();
})->with([
    'no email' => [['id' => '60462']],
    'a blank email' => [['id' => 60462, 'email' => '']],
]);

it('asks to connect again when Wahoo no longer accepts the keys it asks with', function (): void {
    wahooService(new MockClient([
        GetUserRequest::class => MockResponse::make([], 401),
    ]))->account(new ServiceTokens('access'));
})->throws(ServiceRefused::class);

it('says Wahoo is unavailable when it does not say whose account it is', function (MockResponse $mockResponse): void {
    wahooService(new MockClient([
        GetUserRequest::class => $mockResponse,
    ]))->account(new ServiceTokens('access'));
})->with([
    'a failure' => fn (): MockResponse => MockResponse::make([], 503),
    'no number' => fn (): MockResponse => MockResponse::make(['email' => 'road@wahoo.test']),
    'a blank number' => fn (): MockResponse => MockResponse::make(['id' => '']),
])->throws(ServiceUnavailable::class);

it('leaves keys a new log in replaced alone, because withdrawing at Wahoo would end the new ones too', function (): void {
    $mock = new MockClient([]);

    wahooService($mock)->supersede(new ServiceTokens('access-old', 'refresh-old'));

    $mock->assertNothingSent();
});
