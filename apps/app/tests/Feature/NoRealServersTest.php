<?php

declare(strict_types=1);

use App\Account\ConnectionProvider;
use App\Integrations\Nominatim\NominatimConnector;
use App\Integrations\Nominatim\Requests\SearchRequest;
use Illuminate\Http\Client\StrayRequestException as HttpStrayRequestException;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Saloon\Exceptions\StrayRequestException;

/**
 * Tests run on mock data only. A request a test forgot to mock fails instead of
 * reaching a public server, so a slow or missing server never decides a run.
 */
it('stops a Saloon request that has no mock', function (): void {
    new NominatimConnector('https://nominatim.openstreetmap.org')->send(new SearchRequest('Westerlo'));
})->throws(StrayRequestException::class);

it('stops a Laravel HTTP call that has no fake', function (): void {
    Http::get('https://tiles.openfreemap.org/planet');
})->throws(HttpStrayRequestException::class);

it('stops a sign-in service request that has no fake', function (ConnectionProvider $connectionProvider): void {
    config([
        'services.'.$connectionProvider->value.'.client_id' => 'client',
        'services.'.$connectionProvider->value.'.client_secret' => 'secret',
    ]);

    Socialite::driver($connectionProvider->value)->userFromToken('a-token');
})->with(array_filter(
    ConnectionProvider::cases(),
    // Apple reads the rider from its signed token, after fetching its keys with a client of its own.
    static fn (ConnectionProvider $connectionProvider): bool => $connectionProvider !== ConnectionProvider::Apple,
))->throws(RuntimeException::class, 'A test reached the ');
