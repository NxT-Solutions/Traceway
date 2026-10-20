<?php

declare(strict_types=1);

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Account\Email;
use App\Account\RiderName;

covers(ConnectedIdentity::class);

uses()->group('mutation');

/**
 * What a service said about the rider, as the callback hands it over.
 *
 * @param  list<string>  $scopes
 */
function connectedIdentityFrom(
    ConnectionProvider $connectionProvider = ConnectionProvider::Wahoo,
    string $id = '60462',
    ?string $email = 'road@wahoo.test',
    bool $emailVerified = true,
    ?string $name = 'Road Rider',
    ?string $label = 'road@wahoo.test',
    ?ConnectionTokens $connectionTokens = null,
    array $scopes = ['user_read', 'routes_write'],
): ConnectedIdentity {
    return ConnectedIdentity::fromService($connectionProvider, $id, $email, $emailVerified, $name, $label, $connectionTokens, $scopes);
}

it('rejects an account without a number the service gave it', function (string $id): void {
    new ConnectedIdentity(ConnectionProvider::Google, $id);
})->with([
    'empty' => [''],
    'blank' => ['   '],
    'too long' => [str_repeat('9', 192)],
    'a line break' => ["47\n11"],
    'a null byte' => ["47\x0011"],
    'a delete character' => ["47\x7F11"],
])->throws(InvalidArgumentException::class, 'A connected account needs the number the service gave it.');

it('keeps the number as the service gave it, trimmed, up to 191 characters', function (): void {
    expect((new ConnectedIdentity(ConnectionProvider::Google, '  4711 '))->providerUserId)->toBe('4711')
        ->and((new ConnectedIdentity(ConnectionProvider::Google, str_repeat('9', 191)))->providerUserId)->toHaveLength(191)
        ->and((new ConnectedIdentity(ConnectionProvider::Discord, '001234.abcdef-ü'))->providerUserId)->toBe('001234.abcdef-ü');
});

it('trusts an email as confirmed only when there is one', function (): void {
    expect((new ConnectedIdentity(ConnectionProvider::Google, '4711', emailVerified: true))->emailVerified)->toBeFalse()
        ->and((new ConnectedIdentity(ConnectionProvider::Google, '4711', new Email('road@gmail.test'), emailVerified: true))->emailVerified)->toBeTrue()
        ->and((new ConnectedIdentity(ConnectionProvider::Google, '4711', new Email('road@gmail.test')))->emailVerified)->toBeFalse();
});

it('reads what the service sent, normalised', function (): void {
    $connectedIdentity = connectedIdentityFrom(email: ' Road@Wahoo.TEST ', name: "  Road\tRider  ", label: "  road@wahoo.test\n");

    expect($connectedIdentity->provider)->toBe(ConnectionProvider::Wahoo)
        ->and($connectedIdentity->email?->value)->toBe('road@wahoo.test')
        ->and($connectedIdentity->emailVerified)->toBeTrue()
        ->and($connectedIdentity->name?->value)->toBe('Road Rider')
        ->and($connectedIdentity->accountLabel)->toBe('road@wahoo.test')
        ->and($connectedIdentity->scopes)->toBe(['user_read', 'routes_write']);
});

it('drops an email or a name that is not one, rather than refusing the rider', function (?string $email, ?string $name): void {
    $connectedIdentity = connectedIdentityFrom(email: $email, name: $name);

    expect($connectedIdentity->email)->toBeNull()
        ->and($connectedIdentity->emailVerified)->toBeFalse()
        ->and($connectedIdentity->name)->toBeNull()
        ->and($connectedIdentity->providerUserId)->toBe('60462');
})->with([
    'nothing' => [null, null],
    'blank' => ['  ', '  '],
    'not an email, only control characters' => ['road at wahoo', "\x01\x02"],
    'an email that is too long' => [str_repeat('a', 250).'@wahoo.test', "\t"],
]);

it('shortens a long name to what a rider name holds', function (): void {
    expect(connectedIdentityFrom(name: str_repeat('a', 300))->name?->value)->toBe(str_repeat('a', 255));
});

it('keeps the keys only for a service that needs them', function (): void {
    $connectionTokens = new ConnectionTokens('access', 'refresh', 1_792_490_400);

    expect(connectedIdentityFrom(ConnectionProvider::Wahoo, connectionTokens: $connectionTokens)->tokens)->toBe($connectionTokens)
        ->and(connectedIdentityFrom(ConnectionProvider::RideWithGps, connectionTokens: $connectionTokens)->tokens)->toBe($connectionTokens)
        ->and(connectedIdentityFrom(ConnectionProvider::Google, connectionTokens: $connectionTokens)->tokens)->toBeNull()
        ->and(connectedIdentityFrom(ConnectionProvider::Discord, connectionTokens: $connectionTokens)->tokens)->toBeNull();
});

it('keeps an account label only where the service allows it, cleaned and shortened', function (): void {
    expect(connectedIdentityFrom(ConnectionProvider::GitHub, label: "road\x00rider")->accountLabel)->toBe('road rider')
        ->and(connectedIdentityFrom(ConnectionProvider::GitHub, label: " \n ")->accountLabel)->toBeNull()
        ->and(connectedIdentityFrom(ConnectionProvider::GitHub, label: null)->accountLabel)->toBeNull()
        ->and(connectedIdentityFrom(ConnectionProvider::GitHub, label: str_repeat('ü', 200))->accountLabel)->toBe(str_repeat('ü', 191));
});

it('comes back the same from the values it waits in the session as', function (ConnectionProvider $connectionProvider, ?ConnectionTokens $connectionTokens): void {
    $connectedIdentity = connectedIdentityFrom($connectionProvider, connectionTokens: $connectionTokens);

    $back = ConnectedIdentity::fromArray($connectedIdentity->toArray());

    expect($back?->toArray())->toBe($connectedIdentity->toArray())
        ->and($back?->sameAs($connectedIdentity))->toBeTrue();
})->with([
    'with keys' => [ConnectionProvider::Wahoo, new ConnectionTokens('access', 'refresh', 1_792_490_400)],
    'with a key that does not run out' => [ConnectionProvider::RideWithGps, new ConnectionTokens('access')],
    'without keys' => [ConnectionProvider::Google, null],
]);

it('writes its values with the keys and nothing it was not given', function (): void {
    expect(connectedIdentityFrom(ConnectionProvider::Wahoo, connectionTokens: new ConnectionTokens('access', 'refresh', 1_792_490_400))->toArray())->toBe([
        'provider' => 'wahoo',
        'id' => '60462',
        'email' => 'road@wahoo.test',
        'email_verified' => true,
        'name' => 'Road Rider',
        'label' => 'road@wahoo.test',
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expires_at' => 1_792_490_400,
        'scopes' => ['user_read', 'routes_write'],
    ])
        ->and(new ConnectedIdentity(ConnectionProvider::Discord, '4711')->toArray())->toBe([
            'provider' => 'discord',
            'id' => '4711',
            'email' => null,
            'email_verified' => false,
            'name' => null,
            'label' => null,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'scopes' => [],
        ]);
});

it('refuses values that are not an identity', function (array $values): void {
    expect(ConnectedIdentity::fromArray($values))->toBeNull();
})->with([
    'nothing' => [[]],
    'an unknown service' => [['provider' => 'myspace', 'id' => '4711']],
    'a service that is not text' => [['provider' => ['wahoo'], 'id' => '4711']],
    'no number' => [['provider' => 'wahoo']],
    'a number that is not text' => [['provider' => 'wahoo', 'id' => 4711]],
    'a blank number' => [['provider' => 'wahoo', 'id' => '  ']],
]);

it('reads values it does not trust as left out', function (): void {
    $connectedIdentity = ConnectedIdentity::fromArray([
        'provider' => 'wahoo',
        'id' => '60462',
        'email' => ['road@wahoo.test'],
        'email_verified' => 'true',
        'name' => 42,
        'label' => false,
        'access_token' => 'access',
        'refresh_token' => '',
        'expires_at' => '1792490400',
        'scopes' => ['user_read', 7, null, 'routes_write'],
    ]);

    expect($connectedIdentity?->email)->toBeNull()
        ->and($connectedIdentity?->emailVerified)->toBeFalse()
        ->and($connectedIdentity?->name)->toBeNull()
        ->and($connectedIdentity?->accountLabel)->toBeNull()
        ->and($connectedIdentity?->tokens?->accessToken)->toBe('access')
        ->and($connectedIdentity?->tokens?->refreshToken)->toBeNull()
        ->and($connectedIdentity?->tokens?->expiresAt)->toBeNull()
        ->and($connectedIdentity?->scopes)->toBe(['user_read', 'routes_write']);
});

it('keeps no keys from values without an access token', function (mixed $accessToken): void {
    expect(ConnectedIdentity::fromArray(['provider' => 'wahoo', 'id' => '60462', 'access_token' => $accessToken, 'refresh_token' => 'refresh'])?->tokens)->toBeNull();
})->with([
    'none' => [null],
    'empty' => [''],
    'not text' => [123],
]);

it('reads an email that waited unconfirmed as unconfirmed', function (): void {
    $connectedIdentity = ConnectedIdentity::fromArray(['provider' => 'google', 'id' => '4711', 'email' => 'road@gmail.test', 'email_verified' => false, 'scopes' => 'openid']);

    expect($connectedIdentity?->email?->value)->toBe('road@gmail.test')
        ->and($connectedIdentity?->emailVerified)->toBeFalse()
        ->and($connectedIdentity?->scopes)->toBe([])
        ->and(ConnectedIdentity::fromArray(['provider' => 'google', 'id' => '4711', 'email' => 'road@gmail.test'])?->emailVerified)->toBeFalse();
});

it('knows the same account at the same service', function (): void {
    $wahoo = new ConnectedIdentity(ConnectionProvider::Wahoo, '60462', new Email('road@wahoo.test'), name: new RiderName('Road'));

    expect($wahoo->sameAs(new ConnectedIdentity(ConnectionProvider::Wahoo, ' 60462 ')))->toBeTrue()
        ->and($wahoo->sameAs(new ConnectedIdentity(ConnectionProvider::Wahoo, '60463')))->toBeFalse()
        ->and($wahoo->sameAs(new ConnectedIdentity(ConnectionProvider::RideWithGps, '60462')))->toBeFalse();
});
