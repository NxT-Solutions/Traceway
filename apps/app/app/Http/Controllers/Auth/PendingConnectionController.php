<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\Connections\ConnectionFlow;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Drops a service waiting to be connected once the rider logs in, when they decide not to. Any keys it gave are
 * handed back.
 */
class PendingConnectionController extends Controller
{
    public function destroy(Request $request, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $connectionFlow = new ConnectionFlow($request->session());
        $pending = $connectionFlow->pendingLink();

        $connectionFlow->forgetLink();

        if ($pending !== null) {
            $dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($pending['identity']));
        }

        return to_route('login');
    }
}
