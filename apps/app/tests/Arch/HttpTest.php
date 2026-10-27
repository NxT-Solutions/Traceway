<?php

declare(strict_types=1);

use App\Models\SavedRoute;

arch('HTTP dispatches a command or calls a query. It does not construct a handler or send mail')
    ->expect('App\Http')
    ->not->toUse([
        'App\Handlers',
        'App\Mail',
    ]);

arch('a saved route on screen comes from a query, not from the Eloquent model')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        SavedRoute::class,
        'Illuminate\Database\Eloquent',
    ]);

arch('an HTTP action is a controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('validation lives on a form request')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

arch('Fortify actions validate and dispatch. The handler writes the row')
    ->expect('App\Actions')
    ->not->toUse([
        'App\Handlers',
        'App\Queries',
        'App\Integrations',
        'App\Mail',
        'Illuminate\Database\Eloquent',
        ...vendorNamespaces('Saloon'),
    ]);

arch('mail renders a Maizzle template. The handler decides when it is sent')
    ->expect('App\Mail')
    ->not->toUse([
        'App\Commands',
        'App\Handlers',
        'App\Http',
        'App\Models',
        'App\Queries',
        'App\Domain',
        'App\Integrations',
        ...vendorNamespaces('Saloon'),
    ]);

arch('a notification only swaps the Fortify mail body for the Traceway template')
    ->expect('App\Notifications')
    ->not->toUse([
        'App\Commands',
        'App\Handlers',
        'App\Http',
        'App\Queries',
        'App\Integrations',
        ...vendorNamespaces('Saloon'),
    ]);
