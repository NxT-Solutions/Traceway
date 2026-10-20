<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Account\ConnectionFailed;
use App\Account\LinkOutcome;
use App\Account\RiderId;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Impersonation;
use App\Commands\LinkConnectionCommand;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Inertia\Inertia;

/**
 * Connects the service a rider came from once they log in to the account that already had its email. Logging in
 * to any other account connects nothing, so a shared computer cannot hand one person's service to the next. A
 * sign-up left unfinished is dropped too. Whatever is dropped hands back the keys the service gave.
 */
final readonly class ConnectWaitingAccount
{
    public function __construct(private DispatchesCommands $dispatchesCommands) {}

    public function handle(Login $login): void
    {
        $request = request();

        if ($login->guard !== 'web' || ! $request->hasSession() || Impersonation::active($request) || ! $login->user instanceof User) {
            return;
        }

        $connectionFlow = new ConnectionFlow($request->session());
        $signUp = $connectionFlow->pendingSignUp();
        $pending = $connectionFlow->pendingLink();

        $connectionFlow->forgetSignUp();
        $connectionFlow->forgetLink();

        if ($signUp !== null) {
            $this->dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($signUp['identity']));
        }

        if ($pending === null) {
            return;
        }

        $service = $pending['identity']->provider->label();

        if (mb_strtolower($login->user->email) !== $pending['email']->value) {
            $this->dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($pending['identity']));
            Inertia::flash('toast', ['type' => 'info', 'message' => $service.' was not connected, because you logged in to a different account. Connect it in Settings.']);

            return;
        }

        try {
            $linkOutcome = $this->dispatchesCommands->dispatch(new LinkConnectionCommand(new RiderId($login->user->id), $pending['identity']));
        } catch (ConnectionFailed $connectionFailed) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $connectionFailed->getMessage()]);

            return;
        }

        if ($linkOutcome instanceof LinkOutcome) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $service.' connected. Next time you can log in with it.']);
        }
    }
}
