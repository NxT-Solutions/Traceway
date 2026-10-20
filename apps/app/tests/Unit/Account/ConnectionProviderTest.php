<?php

declare(strict_types=1);

use App\Account\ConnectionCapability;
use App\Account\ConnectionGroup;
use App\Account\ConnectionProblem;
use App\Account\ConnectionProvider;
use App\Account\RouteService;
use App\Contracts\Auth\IdentityProviders;
use Tests\TestCase;

covers(ConnectionProvider::class, ConnectionCapability::class, ConnectionGroup::class, ConnectionProblem::class);

uses(TestCase::class)->group('mutation');

/**
 * Every sentence a rider can read about connections, from the catalog, its capabilities and groups, and every
 * problem with every service.
 *
 * @return list<string>
 */
function connectionProviderCopy(): array
{
    $copy = [];

    foreach (ConnectionProvider::cases() as $connectionProvider) {
        $copy[] = $connectionProvider->label();
        $copy[] = $connectionProvider->purpose();
        $copy[] = (string) $connectionProvider->note();
        $copy = [...$copy, ...$connectionProvider->keeps()];

        foreach (ConnectionProblem::cases() as $connectionProblem) {
            $copy[] = $connectionProblem->message($connectionProvider);
        }
    }

    foreach (ConnectionCapability::cases() as $connectionCapability) {
        $copy[] = $connectionCapability->label();
    }

    foreach (ConnectionGroup::cases() as $connectionGroup) {
        $copy[] = $connectionGroup->label();
    }

    return array_values(array_filter($copy, static fn (string $line): bool => $line !== ''));
}

it('describes every service in plain sentences', function (ConnectionProvider $connectionProvider): void {
    expect($connectionProvider->label())->not->toBe('')
        ->and($connectionProvider->purpose())->toEndWith('.')
        ->and($connectionProvider->keeps())->not->toBe([])
        ->and($connectionProvider->keeps())->each->toEndWith('.')
        ->and($connectionProvider->note() ?? 'none.')->toEndWith('.');
})->with(ConnectionProvider::cases());

it('lets every connection log the rider in, and promises later only what it does not do yet', function (ConnectionProvider $connectionProvider): void {
    $capabilities = $connectionProvider->capabilities();

    expect($capabilities[0])->toBe(ConnectionCapability::SignIn)
        ->and(array_intersect(
            array_map(static fn (ConnectionCapability $connectionCapability): string => $connectionCapability->value, $capabilities),
            array_map(static fn (ConnectionCapability $connectionCapability): string => $connectionCapability->value, $connectionProvider->later()),
        ))->toBe([])
        ->and(in_array(ConnectionCapability::SignIn, $connectionProvider->later(), strict: true))->toBeFalse();
})->with(ConnectionProvider::cases());

it('sends routes through exactly the services Send to device knows', function (): void {
    $sending = array_values(array_filter(
        ConnectionProvider::cases(),
        static fn (ConnectionProvider $connectionProvider): bool => in_array(ConnectionCapability::SendRoutes, $connectionProvider->capabilities(), strict: true),
    ));

    expect($sending)->toBe([ConnectionProvider::Wahoo, ConnectionProvider::RideWithGps])
        ->and(ConnectionProvider::Wahoo->routeService())->toBe(RouteService::Wahoo)
        ->and(ConnectionProvider::RideWithGps->routeService())->toBe(RouteService::RideWithGps)
        ->and(ConnectionProvider::GitHub->routeService())->toBeNull()
        ->and(ConnectionProvider::Google->routeService())->toBeNull();
});

it('keeps keys only where Traceway needs them, and then removes its own access', function (ConnectionProvider $connectionProvider): void {
    $keepsKeys = in_array($connectionProvider, [ConnectionProvider::Wahoo, ConnectionProvider::RideWithGps], strict: true);

    expect($connectionProvider->keepsKeys())->toBe($keepsKeys);

    if ($keepsKeys) {
        expect($connectionProvider->manageUrl())->toBeNull();
    }
})->with(ConnectionProvider::cases());

it('keeps the account name every service here allows', function (ConnectionProvider $connectionProvider): void {
    expect($connectionProvider->keepsAccountLabel())->toBeTrue();
})->with(ConnectionProvider::cases());

it('links to a secure page at the service, or to none', function (ConnectionProvider $connectionProvider): void {
    $url = $connectionProvider->manageUrl();

    if ($url === null) {
        expect(in_array($connectionProvider, [ConnectionProvider::Discord, ConnectionProvider::Wahoo, ConnectionProvider::RideWithGps], strict: true))->toBeTrue();

        return;
    }

    expect($url)->toStartWith('https://')
        ->and(filter_var($url, FILTER_VALIDATE_URL))->toBe($url)
        ->and(parse_url($url, PHP_URL_HOST))->toBeString();
})->with(ConnectionProvider::cases());

it('groups the services so a rider finds theirs', function (): void {
    $groups = [];

    foreach (ConnectionProvider::cases() as $connectionProvider) {
        $groups[$connectionProvider->group()->value][] = $connectionProvider->value;
    }

    expect($groups)->toBe([
        'accounts' => ['google', 'github', 'discord'],
        'sport' => ['wahoo', 'ride_with_gps', 'runsignup'],
        'maps' => ['openstreetmap'],
    ])
        ->and(ConnectionGroup::Accounts->label())->toBe('Everyday accounts')
        ->and(ConnectionGroup::Sport->label())->toBe('Sport apps')
        ->and(ConnectionGroup::Maps->label())->toBe('Maps');
});

it('ranks every service in its own place', function (): void {
    $ranks = array_map(static fn (ConnectionProvider $connectionProvider): int => $connectionProvider->rank(), ConnectionProvider::cases());
    sort($ranks);

    expect($ranks)->toBe(range(1, count(ConnectionProvider::cases())))
        ->and(ConnectionProvider::Google->rank())->toBe(1)
        ->and(ConnectionProvider::RunSignup->rank())->toBe(7);
});

it('never uses words that belong to another brand', function (): void {
    foreach (connectionProviderCopy() as $line) {
        expect($line)->not->toMatch('/beacon|kudos|segment|suffer|strava orange/i');
    }
});

it('speaks to the rider without jargon, and says log in', function (): void {
    foreach (connectionProviderCopy() as $line) {
        expect($line)->not->toMatch('/\b(oauth|tokens?|scopes?|sso|oops|we|sign(ed)?[ -]?in)\b/i');
    }
});

it('names the service in what it says went wrong, except where the service does not matter', function (ConnectionProblem $connectionProblem): void {
    $message = $connectionProblem->message(ConnectionProvider::RideWithGps);
    $general = in_array($connectionProblem, [ConnectionProblem::Expired, ConnectionProblem::LastSignInMethod, ConnectionProblem::SignUpClosed], strict: true);

    expect(str_contains($message, 'Ride with GPS'))->toBe(! $general)
        ->and($message)->toEndWith('.');
})->with(ConnectionProblem::cases());

it('tells a rider Wahoo keeps turning down to connect it again, which clears every key Wahoo holds for Traceway', function (): void {
    expect(ConnectionProblem::Refused->message(ConnectionProvider::Wahoo))->toContain('disconnect Wahoo in Settings, under Connections, and connect it again')
        ->and(ConnectionProblem::Refused->message(ConnectionProvider::Google))->toBe('Google turned the request down. Try again.');
});

it('has a slug for each sign-in service in config/services.php, with the keys Socialite needs', function (): void {
    $services = config('services');
    $signIn = array_keys(array_filter(is_array($services) ? $services : [], static fn (mixed $service): bool => is_array($service) && array_key_exists('redirect', $service)));

    expect(ConnectionProvider::slugs())->toEqualCanonicalizing($signIn);

    foreach (ConnectionProvider::slugs() as $slug) {
        expect(config('services.'.$slug))->toHaveKeys(['client_id', 'client_secret', 'redirect']);
    }
});

it('keeps every service off in tests until a test turns it on', function (): void {
    expect(resolve(IdentityProviders::class)->enabled())->toBe([]);
});

it('says GitHub and Discord keep the account number and username, which is all their connection stores', function (ConnectionProvider $connectionProvider): void {
    expect($connectionProvider->keeps()[0])->toBe('Your '.$connectionProvider->label().' account number and username.');
})->with([
    'GitHub' => ConnectionProvider::GitHub,
    'Discord' => ConnectionProvider::Discord,
]);
