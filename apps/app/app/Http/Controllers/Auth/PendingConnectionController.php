<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Account\ConnectionFailed;
use App\Account\LinkOutcome;
use App\Account\RiderId;
use App\Auth\Connections\ConnectionFlow;
use App\Commands\LinkConnectionCommand;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A service waiting to be connected once the rider logs in. When the service confirmed no email of the account,
 * the rider says yes here, with the service's account named, before it is connected. "Not now" drops it, and any
 * keys it gave are handed back.
 */
class PendingConnectionController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $pending = new ConnectionFlow($request->session())->pendingLink();

        if ($pending === null || ! $pending['confirm']) {
            return redirect()->to($this->after($request));
        }

        $connectedIdentity = $pending['identity'];
        $user = $request->user();

        return Inertia::render('auth/connect-pending', [
            'service' => ['slug' => $connectedIdentity->provider->value, 'label' => $connectedIdentity->provider->label()],
            'account' => $connectedIdentity->accountLabel,
            'email' => $user instanceof User ? $user->email : '',
        ]);
    }

    public function store(Request $request, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $connectionFlow = new ConnectionFlow($request->session());
        $pending = $connectionFlow->pendingLink();
        $user = $request->user();

        $connectionFlow->forgetLink();

        if ($pending === null || ! $user instanceof User) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'That took too long. Connect the service in Settings, under Connections.']);

            return to_route('connections.edit');
        }

        $service = $pending['identity']->provider->label();

        try {
            $linkOutcome = $dispatchesCommands->dispatch(new LinkConnectionCommand(new RiderId($user->id), $pending['identity']));
        } catch (ConnectionFailed $connectionFailed) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $connectionFailed->getMessage()]);

            return to_route('connections.edit');
        }

        if ($linkOutcome instanceof LinkOutcome) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $service.' connected. Next time you can log in with it.']);
        }

        return redirect()->to($this->after($request));
    }

    public function destroy(Request $request, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $connectionFlow = new ConnectionFlow($request->session());
        $pending = $connectionFlow->pendingLink();

        $connectionFlow->forgetLink();

        if ($pending !== null) {
            $dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($pending['identity']));
        }

        return $request->user() instanceof User ? redirect()->to($this->after($request)) : to_route('login');
    }

    /**
     * Where the rider was going before this page, such as an AI assistant's approval page.
     */
    private function after(Request $request): string
    {
        return new ConnectionFlow($request->session())->pullAfter() ?? route('dashboard');
    }
}
