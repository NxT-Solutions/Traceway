<?php

declare(strict_types=1);

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\ServiceAccount;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Wahoo, set up as a service that remembers the keys Traceway handed back.
 */
function withdrawConnectionAccessWahoo(): RecordingCourseService
{
    config(['services.wahoo.client_id' => 'wahoo-client', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $recordingCourseService = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $recordingCourseService]));

    return $recordingCourseService;
}

/**
 * Hands back the keys of a Wahoo sign-up nobody finished.
 */
function withdrawConnectionAccessAbandon(): void
{
    resolve(DispatchesCommands::class)->dispatch(new WithdrawConnectionAccessCommand(new ConnectedIdentity(
        ConnectionProvider::Wahoo,
        '8080',
        connectionTokens: new ConnectionTokens('access-abandoned', 'refresh-abandoned', now()->addHour()->getTimestamp()),
    )));
}

/**
 * A connection carried over from before connections had the service's number.
 *
 * @param  array<string, mixed>  $attributes
 */
function withdrawConnectionAccessCarriedOver(array $attributes = []): ConnectedAccount
{
    return ConnectedAccount::factory()->withoutIdentity()->create([
        'access_token' => 'access-carried-over',
        'connected_at' => now()->subYear(),
        ...$attributes,
    ]);
}

it('hands back the keys of a sign-up nobody finished', function (): void {
    $recordingCourseService = withdrawConnectionAccessWahoo();

    withdrawConnectionAccessAbandon();

    expect($recordingCourseService->revoked)->toBe(['access-abandoned']);
});

it('keeps the keys while a connection from before, with keys that still work, could be the same account', function (): void {
    $recordingCourseService = withdrawConnectionAccessWahoo();
    withdrawConnectionAccessCarriedOver();

    withdrawConnectionAccessAbandon();

    expect($recordingCourseService->revoked)->toBe([]);
});

it('hands the keys back when the only connections without a number have nothing left to lose', function (array $attributes): void {
    $recordingCourseService = withdrawConnectionAccessWahoo();
    withdrawConnectionAccessCarriedOver($attributes);

    withdrawConnectionAccessAbandon();

    expect($recordingCourseService->revoked)->toBe(['access-abandoned']);
})->with([
    'keys the service turned down' => [['needs_reconnect' => true]],
    'no keys' => [['access_token' => null, 'refresh_token' => null, 'expires_at' => null]],
]);

it('hands the keys back once the nightly run finds that Wahoo turned down the keys of a connection from before', function (): void {
    $recordingCourseService = withdrawConnectionAccessWahoo();
    $recordingCourseService->failure = 'refused';
    withdrawConnectionAccessCarriedOver();

    $this->artisan('connections:identify')->assertSuccessful();
    $recordingCourseService->failure = null;
    withdrawConnectionAccessAbandon();

    expect($recordingCourseService->revoked)->toBe(['access-abandoned']);
});

it("hands the keys back once the nightly run finds that a connection from before is another rider's account", function (): void {
    $recordingCourseService = withdrawConnectionAccessWahoo();
    $recordingCourseService->accounts['access-carried-over'] = new ServiceAccount('60462');
    ConnectedAccount::factory()->for(User::factory())->create(['provider_user_id' => '60462']);
    withdrawConnectionAccessCarriedOver();

    $this->artisan('connections:identify')->assertSuccessful();
    withdrawConnectionAccessAbandon();

    expect($recordingCourseService->revoked)->toBe(['access-abandoned']);
});
