<?php

declare(strict_types=1);

use App\Mcp\Servers\TracewayServer;
use Laravel\Mcp\Facades\Mcp;

// ChatGPT, Claude, and other AI tools find the sign-in through these routes,
// register themselves, and ask the person for permission.
Mcp::oauthRoutes();

Mcp::web('mcp', TracewayServer::class)
    ->middleware(['auth:api', 'throttle:mcp'])
    ->name('mcp');
