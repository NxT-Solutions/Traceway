<?php

declare(strict_types=1);

use App\Models\SavedRoute;

arch('commands are immutable data')
    ->expect('App\Commands')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly()
    ->not->toUse([
        'Illuminate\Database',
        'Illuminate\Support\Facades',
        'App\Models',
    ]);

arch('queries read through the query builder')
    ->expect('App\Queries')
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'App\Models',
    ]);

arch('handlers are final')
    ->expect('App\Handlers')
    ->classes()
    ->toBeFinal();

arch('controllers do not load saved route models')
    ->expect('App\Http\Controllers')
    ->not->toUse(SavedRoute::class);
