<?php

declare(strict_types=1);

use App\Domain\Connections\GrantedAccesses;
use App\Domain\Courses\Services\ServiceTokens;
use Tests\Fakes\RecordingGrantedAccess;

covers(GrantedAccesses::class);

uses()->group('mutation');

const GRANTED_ACCESSES_NOW = 1_792_490_400;

it('knows which services it can hand access back to', function (): void {
    $grantedAccesses = new GrantedAccesses(['garmin_connect' => new RecordingGrantedAccess]);

    expect($grantedAccesses->has('garmin_connect'))->toBeTrue()
        ->and($grantedAccesses->has('google'))->toBeFalse();
});

it('withdraws access with the keys it has while they still work', function (): void {
    $recordingGrantedAccess = new RecordingGrantedAccess;

    new GrantedAccesses(['garmin_connect' => $recordingGrantedAccess])
        ->withdraw('garmin_connect', new ServiceTokens('access', 'refresh', GRANTED_ACCESSES_NOW + 3600), GRANTED_ACCESSES_NOW);

    expect($recordingGrantedAccess->refreshed)->toBe([])
        ->and($recordingGrantedAccess->revoked)->toBe(['access']);
});

it('withdraws access with keys that do not run out', function (): void {
    $recordingGrantedAccess = new RecordingGrantedAccess;

    new GrantedAccesses(['ride_with_gps' => $recordingGrantedAccess])
        ->withdraw('ride_with_gps', new ServiceTokens('access'), GRANTED_ACCESSES_NOW);

    expect($recordingGrantedAccess->refreshed)->toBe([])
        ->and($recordingGrantedAccess->revoked)->toBe(['access']);
});

it('refreshes keys that ran out or are about to, so the service does not keep its access', function (int $expiresAt): void {
    $recordingGrantedAccess = new RecordingGrantedAccess;

    new GrantedAccesses(['garmin_connect' => $recordingGrantedAccess])
        ->withdraw('garmin_connect', new ServiceTokens('stale', 'refresh', $expiresAt), GRANTED_ACCESSES_NOW);

    expect($recordingGrantedAccess->refreshed)->toBe(['stale'])
        ->and($recordingGrantedAccess->revoked)->toBe(['access-refreshed']);
})->with([
    'an hour ago' => [GRANTED_ACCESSES_NOW - 3600],
    'now' => [GRANTED_ACCESSES_NOW],
    'within the minute' => [GRANTED_ACCESSES_NOW + 60],
]);

it('has nothing to ask a service that is not set up', function (): void {
    $recordingGrantedAccess = new RecordingGrantedAccess;

    new GrantedAccesses(['garmin_connect' => $recordingGrantedAccess])
        ->withdraw('wahoo', new ServiceTokens('access', 'refresh', GRANTED_ACCESSES_NOW - 3600), GRANTED_ACCESSES_NOW);

    expect($recordingGrantedAccess->refreshed)->toBe([])
        ->and($recordingGrantedAccess->revoked)->toBe([]);
});

it('refreshes the keys of access a service gave', function (): void {
    $recordingGrantedAccess = new RecordingGrantedAccess;

    $serviceTokens = new GrantedAccesses(['garmin_connect' => $recordingGrantedAccess])
        ->refresh('garmin_connect', new ServiceTokens('access', 'refresh', GRANTED_ACCESSES_NOW + 3600));

    expect($serviceTokens->accessToken)->toBe('access-refreshed')
        ->and($recordingGrantedAccess->refreshed)->toBe(['access'])
        ->and($recordingGrantedAccess->revoked)->toBe([]);
});

it('cannot refresh access at a service that is not set up', function (): void {
    new GrantedAccesses(['garmin_connect' => new RecordingGrantedAccess])
        ->refresh('wahoo', new ServiceTokens('access', 'refresh'));
})->throws(InvalidArgumentException::class, 'No access is kept for wahoo.');

it('lets go of the keys a new log in replaced', function (): void {
    $recordingGrantedAccess = new RecordingGrantedAccess;

    new GrantedAccesses(['ride_with_gps' => $recordingGrantedAccess])
        ->supersede('ride_with_gps', new ServiceTokens('access-old'), new ServiceTokens('access-new'));

    expect($recordingGrantedAccess->superseded)->toBe(['access-old'])
        ->and($recordingGrantedAccess->revoked)->toBe([]);
});

it('keeps the keys when the service handed back the same ones, or is not set up', function (string $slug, string $new): void {
    $recordingGrantedAccess = new RecordingGrantedAccess;

    new GrantedAccesses(['ride_with_gps' => $recordingGrantedAccess])
        ->supersede($slug, new ServiceTokens('access-old'), new ServiceTokens($new));

    expect($recordingGrantedAccess->superseded)->toBe([]);
})->with([
    'the same key' => ['ride_with_gps', 'access-old'],
    'a service that is not set up' => ['wahoo', 'access-new'],
]);
