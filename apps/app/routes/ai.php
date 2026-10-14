<?php

declare(strict_types=1);

use App\Http\Controllers\ShowRouteMapScriptController;
use App\Mcp\Servers\TracewayServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// ChatGPT, Claude, and other AI tools find the sign-in through these routes,
// register themselves, and ask the person for permission.
Mcp::oauthRoutes();

Mcp::web('mcp', TracewayServer::class)
    ->middleware(['auth:api', 'throttle:mcp'])
    ->name('mcp');

// The map in the chat card fetches this each time it shows. See RouteMapApp.
Route::get('mcp/apps/route-map.js', ShowRouteMapScriptController::class)
    ->middleware('throttle:60,1')
    ->name('mcp.apps.route-map');
