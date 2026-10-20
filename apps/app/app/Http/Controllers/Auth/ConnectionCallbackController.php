<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Account\ConnectedIdentity;
use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Account\Email;
use App\Account\LinkOutcome;
use App\Account\Rider;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Account\RouteService;
use App\Auth\Connections\AppleCallbackTicket;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\ConnectionIntent;
use App\Auth\Connections\ConnectionSignIn;
use App\Commands\LinkConnectionCommand;
use App\Commands\OpenAccountWithConnectionCommand;
use App\Commands\RecordConnectionSignInCommand;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Auth\IdentityProviders;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Account\ConnectionsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;

/**
 * Where every service sends the rider back. The answer is used only for what the rider set out to do: log in or
 * open an account, connect the service from Settings, or prove it is them. An answer that did not start here is
 * refused, so another site cannot log someone in to the wrong account or attach its own account to theirs.
 */
class ConnectionCallbackController extends Controller
{
    public function __construct(
        private readonly IdentityProviders $identityProviders,
        private readonly DispatchesCommands $dispatchesCommands,
        private readonly ConnectionsQuery $connectionsQuery,
        private readonly ConnectionSignIn $connectionSignIn,
    ) {}

    public function __invoke(Request $request, string $provider, AppleCallbackTicket $appleCallbackTicket): RedirectResponse
    {
        $connectionProvider = ConnectionProvider::tryFrom($provider);

        abort_unless($connectionProvider instanceof ConnectionProvider && $this->identityProviders->isEnabled($connectionProvider), 404);

        // Apple's answer waits under a ticket. One that was used already, or ran out, came too late.
        $answered = $connectionProvider !== ConnectionProvider::Apple || $this->takeAppleAnswer($request, $appleCallbackTicket);

        $connectionFlow = new ConnectionFlow($request->session());
        $flow = $connectionFlow->finish($connectionProvider);

        if ($flow === null) {
            return $this->failed($request, $connectionProvider, ConnectionProblem::Expired, connectionIntent: null, return: null);
        }

        if (! $answered) {
            return $this->failed($request, $connectionProvider, ConnectionProblem::Expired, $flow['intent'], $flow['return']);
        }

        $connectedIdentity = null;

        try {
            $connectedIdentity = $this->identityProviders->identity($connectionProvider);

            return match ($flow['intent']) {
                ConnectionIntent::SignIn => $this->signIn($request, $connectionFlow, $connectedIdentity, $flow['remember']),
                ConnectionIntent::Connect => $this->connect($request, $connectedIdentity, $flow['rider'], $flow['return']),
                ConnectionIntent::Confirm => $this->confirm($request, $connectedIdentity, $flow['rider']),
            };
        } catch (ConnectionFailed $connectionFailed) {
            // The service gave keys for an answer that went nowhere. They go back, unless a rider has that account.
            if ($connectedIdentity instanceof ConnectedIdentity) {
                $this->dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($connectedIdentity));
            }

            return $this->failed($request, $connectionProvider, $connectionFailed->problem, $flow['intent'], $flow['return']);
        }
    }

    /**
     * A rider who connected this account logs in. A new rider opens an account: at once when the service
     * confirmed their email, after they give or confirm one otherwise. An email that already has an account is
     * never linked by itself. When the service confirmed it, the rider logs in to that account first. Any other
     * address could be someone else's, so the rider connects the service from Settings instead.
     *
     * @throws ConnectionFailed when no account opens for the answer
     */
    private function signIn(Request $request, ConnectionFlow $connectionFlow, ConnectedIdentity $connectedIdentity, bool $remember): RedirectResponse
    {
        $connectionProvider = $connectedIdentity->provider;

        if ($request->user() instanceof User) {
            $this->dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($connectedIdentity));

            return redirect()->intended(route('dashboard'));
        }

        $riderId = $this->connectionsQuery->riderFor($connectionProvider, $connectedIdentity->providerUserId);

        if ($riderId !== null) {
            $this->dispatchesCommands->dispatch(new RecordConnectionSignInCommand(new RiderId($riderId), $connectedIdentity));

            return $this->connectionSignIn->logIn($request, new RiderId($riderId), $connectionProvider, $remember);
        }

        $email = $connectedIdentity->email;

        // Before the sign-up check, since an existing rider opens no account.
        if ($email instanceof Email && $this->connectionsQuery->emailTaken($email->value)) {
            if (! $connectedIdentity->emailVerified) {
                throw ConnectionFailed::because($connectionProvider, ConnectionProblem::EmailTaken);
            }

            $connectionFlow->awaitLogin($connectedIdentity, $email);

            return to_route('login');
        }

        if (! Features::enabled(Features::registration())) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::SignUpClosed);
        }

        if (! $email instanceof Email || ! $connectedIdentity->emailVerified) {
            $connectionFlow->awaitSignUp($connectedIdentity, $remember);

            return to_route('connections.finish');
        }

        $rider = $this->dispatchesCommands->dispatch(new OpenAccountWithConnectionCommand(
            $connectedIdentity,
            $connectedIdentity->name ?? new RiderName(explode('@', $email->value)[0]),
            $email,
        ));

        abort_unless($rider instanceof Rider, 500);

        return $this->connectionSignIn->logIn($request, $rider->id, $connectionProvider, $remember);
    }

    /**
     * Adds the account to the rider who started from Settings, and to nobody else.
     *
     * @throws ConnectionFailed when another rider started, or another rider has the account
     */
    private function connect(Request $request, ConnectedIdentity $connectedIdentity, ?int $startedBy, ?string $return): RedirectResponse
    {
        $connectionProvider = $connectedIdentity->provider;
        $user = $request->user();

        if (! $user instanceof User || $user->id !== $startedBy) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Expired);
        }

        $linkOutcome = $this->dispatchesCommands->dispatch(new LinkConnectionCommand(new RiderId($user->id), $connectedIdentity));

        $service = $connectionProvider->label();
        $message = match (true) {
            $linkOutcome === LinkOutcome::Refreshed => $service.' is connected again.',
            $connectionProvider->routeService() instanceof RouteService => $service.' connected. Send a route to it from Send to device, or log in with it.',
            default => $service.' connected. You can log in with it now.',
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->to($return ?? route('connections.edit'));
    }

    /**
     * The service vouches for the rider in place of a password, but only for the account connected to them.
     *
     * @throws ConnectionFailed when another rider started, or the account is not the one connected
     */
    private function confirm(Request $request, ConnectedIdentity $connectedIdentity, ?int $startedBy): RedirectResponse
    {
        $connectionProvider = $connectedIdentity->provider;
        $user = $request->user();

        if (! $user instanceof User || $user->id !== $startedBy) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Expired);
        }

        if ($this->connectionsQuery->identityOf($user->id, $connectionProvider) !== $connectedIdentity->providerUserId) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::NotLinked);
        }

        // The service gave new keys for this round trip. They take the place of the kept ones, which are let go,
        // so no key is left working at the service that Traceway no longer holds.
        if ($connectedIdentity->tokens instanceof ConnectionTokens) {
            $this->dispatchesCommands->dispatch(new LinkConnectionCommand(new RiderId($user->id), $connectedIdentity));
        }

        // What a confirmed password or passkey records, so the step the rider was on lets them through.
        $request->session()->put('auth.password_confirmed_at', now()->getTimestamp());

        return redirect()->intended(route('connections.edit'));
    }

    private function failed(Request $request, ConnectionProvider $connectionProvider, ConnectionProblem $connectionProblem, ?ConnectionIntent $connectionIntent, ?string $return): RedirectResponse
    {
        $message = $connectionProblem->message($connectionProvider);
        $signedIn = $request->user() instanceof User;

        if ($connectionIntent === ConnectionIntent::Confirm && $signedIn) {
            return to_route('password.confirm')->withErrors(['connection' => $message]);
        }

        if ($connectionIntent === ConnectionIntent::Connect || (! $connectionIntent instanceof ConnectionIntent && $signedIn)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return redirect()->to($return ?? route('connections.edit'));
        }

        return to_route('login')->withErrors(['connection' => $message]);
    }

    /**
     * Puts Apple's held answer back on the request, where Socialite reads it. False when there is none.
     */
    private function takeAppleAnswer(Request $request, AppleCallbackTicket $appleCallbackTicket): bool
    {
        $ticket = $request->query('ticket');
        $answer = is_string($ticket) ? $appleCallbackTicket->redeem($ticket) : null;

        $request->query->remove('ticket');

        if ($answer === null) {
            return false;
        }

        $request->merge($answer);

        return true;
    }
}
