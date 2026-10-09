<?php

declare(strict_types=1);

arch('domain does not reach http, eloquent, or integrations')
    ->expect('App\Domain')
    ->not->toUse([
        'Saloon',
        'App\Integrations',
        'App\Models',
        'Illuminate\Http',
        'Illuminate\Database\Eloquent',
    ]);

arch('integrations do not reach http controllers')
    ->expect('App\Integrations')
    ->not->toUse('App\Http\Controllers');

arch('domain types stay final')
    ->expect('App\Domain')
    ->classes()
    ->toBeFinal();
