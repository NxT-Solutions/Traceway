<?php

declare(strict_types=1);

arch('a command carries value objects. It does not touch the database, a facade, or a model')
    ->expect('App\Commands')
    ->toOnlyUse([
        'App\Commands',
        'App\Account',
        'App\Routes',
        'App\Domain',
    ]);

arch('a command is not a base class')
    ->expect('App\Commands')
    ->classes()
    ->toBeFinal();

arch('a command does not change after it is built')
    ->expect('App\Commands')
    ->classes()
    ->toBeReadonly();

arch('a command is named for the write. Results live in App\Routes')
    ->expect('App\Commands')
    ->toHaveSuffix('Command');

arch('one command has one handler')
    ->expect('App\Handlers')
    ->classes()
    ->toBeFinal();

arch('a handler is the application service for one command')
    ->expect('App\Handlers')
    ->toHaveSuffix('Handler');

arch('a handler talks to contracts. Repositories persist, the mailer sends, and the session rider authorizes')
    ->expect('App\Handlers')
    ->not->toUse([
        'App\Bus',
        'App\Http',
        'App\Integrations',
        'App\Mail',
        'App\Models',
        'App\Repositories',
        'Illuminate\Database\Eloquent',
        'Illuminate\Support\Facades',
        ...vendorNamespaces('Saloon'),
    ]);

arch('only the service provider wires a handler. Callers dispatch a command')
    ->expect('App\Handlers')
    ->toOnlyBeUsedIn(within('App\Providers', 'App\Handlers'));

arch('a read model is a closed projection')
    ->expect('App\Queries')
    ->classes()
    ->toBeFinal();

arch('a query uses the query builder and returns arrays or a readonly DTO')
    ->expect('App\Queries')
    ->not->toUse([
        'App\Commands',
        'App\Handlers',
        'App\Http',
        'App\Models',
        'App\Integrations',
        'App\Mail',
        'Illuminate\Database\Eloquent',
        ...vendorNamespaces('Saloon'),
    ]);

arch('the bus maps a command class to a handler class. The provider owns that map')
    ->expect('App\Bus')
    ->toOnlyUse([
        'Illuminate\Contracts\Container',
        'App\Contracts\Bus',
    ])
    ->ignoring(['throw_if', 'throw_unless']);

arch('there is one command bus')
    ->expect('App\Bus')
    ->classes()
    ->toBeFinal();

arch('the handler map does not change after registration')
    ->expect('App\Bus')
    ->classes()
    ->toBeReadonly();

arch('callers use the DispatchesCommands contract. The provider builds the concrete bus')
    ->expect('App\Bus')
    ->toOnlyBeUsedIn(within('App\Providers', 'App\Bus'));

arch('an Eloquent repository is one adapter, not a base class')
    ->expect('App\Repositories')
    ->classes()
    ->toBeFinal();

arch('a repository persists. It does not mail, route, or render')
    ->expect('App\Repositories')
    ->not->toUse([
        'App\Handlers',
        'App\Http',
        'App\Mail',
        'App\Integrations',
        ...vendorNamespaces('Saloon'),
    ]);

arch('handlers depend on the repository contract. Only the provider chooses the Eloquent adapter')
    ->expect('App\Repositories')
    ->toOnlyBeUsedIn(within('App\Providers', 'App\Repositories'));
