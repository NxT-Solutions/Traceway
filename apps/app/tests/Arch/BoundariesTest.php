<?php

declare(strict_types=1);

arch('sport rules and course files stay free of HTTP, Eloquent, Saloon, and the application layers')
    ->expect('App\Domain')
    ->toOnlyUse('App\Domain');

arch('a domain class is closed. Enums and the routing port stay as they are')
    ->expect('App\Domain')
    ->classes()
    ->toBeFinal();

arch('an integration speaks Saloon and returns domain types')
    ->expect('App\Integrations')
    ->toOnlyUse([
        'App\Domain',
        'App\Integrations',
        'Saloon',
    ])
    ->ignoring(['throw_if', 'throw_unless', 'value']);

arch('a connector, request, and routing engine are closed adapters')
    ->expect('App\Integrations')
    ->classes()
    ->toBeFinal();

arch('a contract is a port. It does not know Eloquent, HTTP, or a concrete adapter')
    ->expect('App\Contracts')
    ->toOnlyUse([
        'App\Contracts',
        'App\Commands',
        'App\Account',
        'App\Routes',
    ]);

arch('the sign-in adapter returns identities. Controllers decide what to do with them')
    ->expect('App\Auth\Connections')
    ->not->toUse([
        'App\Http',
        'App\Handlers',
        'App\Repositories',
        'Saloon',
    ]);

arch('outbound HTTP is a Saloon connector under App\Integrations')
    ->expect('App')
    ->not->toUse('Saloon')
    ->ignoring('App\Integrations');

arch('logging in with another service is a Socialite handshake in App\Auth\Connections')
    ->expect('App')
    ->not->toUse([
        'Laravel\Socialite',
        'SocialiteProviders',
        'GuzzleHttp',
    ])
    ->ignoring([
        'App\Auth\Connections',
        'App\Providers',
    ]);

arch('Eloquent stays on the models. A query uses the query builder')
    ->expect('App')
    ->not->toUse('Illuminate\Database\Eloquent')
    ->ignoring([
        'App\Models',
        'App\Repositories',
    ]);
