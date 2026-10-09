<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Commands\DeleteRouteCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\User;
use App\Routes\RouteId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroySavedRouteController extends Controller
{
    public function __invoke(Request $request, string $savedRoute, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new DeleteRouteCommand(
            routeId: new RouteId($savedRoute),
            riderId: new RiderId($user->id),
        ));

        return to_route('routes.library');
    }
}
