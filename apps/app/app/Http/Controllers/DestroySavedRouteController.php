<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroySavedRouteController extends Controller
{
    public function __invoke(Request $request, SavedRoute $savedRoute): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $savedRoute->user_id !== $user->id) {
            abort(403);
        }

        $savedRoute->delete();

        return to_route('routes.library');
    }
}
