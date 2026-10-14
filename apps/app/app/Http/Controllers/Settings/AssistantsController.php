<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Account\ConnectedApp;
use App\Queries\Account\ConnectedAppsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where a rider connects an AI assistant to Traceway. Traceway is an MCP
 * connector, so Claude, ChatGPT, Codex, Cursor, and VS Code can plan
 * routes and save them to the library.
 */
class AssistantsController extends Controller
{
    public function edit(Request $request, ConnectedAppsQuery $connectedAppsQuery): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('settings/assistants', [
            'connector_url' => url('/mcp'),
            'apps' => array_map(fn (ConnectedApp $connectedApp): array => $connectedApp->toArray(), $connectedAppsQuery->forRider($user->id)),
        ]);
    }
}
