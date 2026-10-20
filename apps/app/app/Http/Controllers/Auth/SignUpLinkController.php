<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\Rider;
use App\Auth\Connections\ConnectionSignIn;
use App\Auth\Connections\PendingSignUps;
use App\Commands\OpenAccountWithConnectionCommand;
use App\Commands\OpenAccountWithoutConnectionCommand;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Account\ConnectionsQuery;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * The link Traceway emails a new rider who came from a service. Opening it shows what is about to happen, and
 * only the button opens the account, so a mail scanner that opens every link first does not use it up. Using
 * the link proves the address, so the account opens with the email confirmed.
 *
 * The service is connected only in the browser that asked for the link. Anyone else with the email, such as the
 * owner of an address another person typed, gets the account without that person's service.
 */
class SignUpLinkController extends Controller
{
    public function show(Request $request, string $token, PendingSignUps $pendingSignUps, ConnectionsQuery $connectionsQuery): Response|RedirectResponse
    {
        $pending = $pendingSignUps->find($token);

        if ($pending === null) {
            return $this->expired();
        }

        $connectionProvider = $pending['identity']->provider;

        if ($connectionsQuery->emailTaken($pending['email']->value)) {
            return to_route('login')->withErrors(['connection' => ConnectionProblem::EmailTaken->message($connectionProvider)]);
        }

        $connects = PendingSignUps::sameBrowser($pending, $request);

        return Inertia::render('auth/finish-sign-up-confirm', [
            'service' => ['slug' => $connectionProvider->value, 'label' => $connectionProvider->label()],
            // Which account at the service gets connected, so someone who did not start this on a shared computer
            // sees it is not theirs.
            'account' => $connects ? $pending['identity']->accountLabel : null,
            'name' => $pending['name']->value,
            'email' => $pending['email']->value,
            'token' => $token,
            'connects' => $connects,
        ]);
    }

    public function store(Request $request, string $token, PendingSignUps $pendingSignUps, DispatchesCommands $dispatchesCommands, ConnectionSignIn $connectionSignIn): RedirectResponse
    {
        $pending = $pendingSignUps->take($token);

        if ($pending === null) {
            return $this->expired();
        }

        $connectedIdentity = $pending['identity'];
        $connectionProvider = $connectedIdentity->provider;
        $connects = PendingSignUps::sameBrowser($pending, $request);

        if (! Features::enabled(Features::registration())) {
            $dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($connectedIdentity));

            return to_route('login')
                ->withErrors(['connection' => ConnectionProblem::SignUpClosed->message($connectionProvider)])
                ->withCookie(PendingSignUps::forgetCookie());
        }

        try {
            $rider = $dispatchesCommands->dispatch($connects
                ? new OpenAccountWithConnectionCommand($connectedIdentity, $pending['name'], $pending['email'], emailConfirmed: true)
                : new OpenAccountWithoutConnectionCommand($connectionProvider, $pending['name'], $pending['email']));
        } catch (ConnectionFailed $connectionFailed) {
            // The email, or the account at the service, got a Traceway account since the link was sent.
            $dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($connectedIdentity));

            return to_route('login')
                ->withErrors(['connection' => $connectionFailed->getMessage()])
                ->withCookie(PendingSignUps::forgetCookie());
        }

        abort_unless($rider instanceof Rider, 500);

        $user = Auth::guard('web')->getProvider()->retrieveById($rider->id->value);

        abort_unless($user instanceof User, 500);

        // The link proved the address, as Traceway's own confirmation link does, so it counts the same.
        event(new Verified($user));

        if ($connects) {
            return $connectionSignIn->logIn($request, $rider->id, $connectionProvider, $pending['remember'])
                ->withCookie(PendingSignUps::forgetCookie());
        }

        $dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($connectedIdentity));

        // A new account has no two-factor sign-in to ask for. Remember me was chosen in the other browser.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // The link proved the address, as a reset link does, so connecting the service or setting a password
        // does not ask the rider to confirm it is them again.
        $request->session()->put('auth.password_confirmed_at', now()->getTimestamp());

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Your account is open. To log in with '.$connectionProvider->label().', connect it in Settings, under Connections.']);

        return redirect()->intended(Fortify::redirects('login'))->withCookie(PendingSignUps::forgetCookie());
    }

    private function expired(): RedirectResponse
    {
        return to_route('login')->withErrors(['connection' => 'That link has expired or was used. Choose the account again to sign up.']);
    }
}
