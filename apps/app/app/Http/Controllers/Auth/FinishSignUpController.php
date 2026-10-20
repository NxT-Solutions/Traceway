<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Account\ConnectionProblem;
use App\Account\Email;
use App\Account\RiderName;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\PendingSignUps;
use App\Commands\SendConnectionSignUpLinkCommand;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Middleware\GuardSignupForms;
use App\Http\Requests\Auth\FinishSignUpRequest;
use App\Queries\Account\ConnectionsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * The last step for a new rider whose service gave no email, or one it did not confirm. They give or confirm
 * their name and email, and Traceway emails a link. Nobody has shown the address is theirs yet, so the account
 * opens only when that link is used, and the service is connected to it only in this browser.
 */
class FinishSignUpController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $connectionFlow = new ConnectionFlow($request->session());
        $pending = $connectionFlow->pendingSignUp();

        if ($pending === null) {
            return $this->nothingWaiting($connectionFlow);
        }

        $connectedIdentity = $pending['identity'];

        return Inertia::render('auth/finish-sign-up', [
            'service' => ['slug' => $connectedIdentity->provider->value, 'label' => $connectedIdentity->provider->label()],
            'name' => $connectedIdentity->name->value ?? '',
            'email' => $connectedIdentity->email->value ?? '',
            'turnstileSiteKey' => GuardSignupForms::siteKey(),
        ]);
    }

    public function store(FinishSignUpRequest $finishSignUpRequest, DispatchesCommands $dispatchesCommands, ConnectionsQuery $connectionsQuery, PendingSignUps $pendingSignUps): RedirectResponse
    {
        $connectionFlow = new ConnectionFlow($finishSignUpRequest->session());
        $pending = $connectionFlow->pendingSignUp();

        if ($pending === null) {
            return $this->nothingWaiting($connectionFlow);
        }

        $connectedIdentity = $pending['identity'];
        $connectionProvider = $connectedIdentity->provider;

        $connectionFlow->forgetSignUp();

        if (! Features::enabled(Features::registration())) {
            $dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($connectedIdentity));

            return to_route('login')->withErrors(['connection' => ConnectionProblem::SignUpClosed->message($connectionProvider)]);
        }

        $riderName = new RiderName($finishSignUpRequest->name());
        $email = new Email($finishSignUpRequest->email());

        // An address typed here, or one the service did not confirm, could be anyone's. It never connects the
        // service by itself: the rider logs in to the account that has it, then says yes to connecting it.
        if ($connectionsQuery->emailTaken($email->value)) {
            $connectionFlow->awaitLogin($connectedIdentity, $email, confirm: true);

            return to_route('login');
        }

        $held = $pendingSignUps->hold($connectedIdentity, $riderName, $email, $pending['remember'], $finishSignUpRequest);
        $dispatchesCommands->dispatch(new SendConnectionSignUpLinkCommand($riderName, $email, $connectionProvider, $held['token']));
        $connectionFlow->signUpSent($email);

        return to_route('login')
            ->with('status', 'Check your email. A link to finish your account is on its way to '.$email->value.'.')
            ->withCookie($held['cookie']);
    }

    /**
     * The rider already has a Traceway account. They log in to it, then say yes to connecting the service.
     */
    public function existing(Request $request): RedirectResponse
    {
        $connectionFlow = new ConnectionFlow($request->session());
        $pending = $connectionFlow->pendingSignUp();

        if ($pending === null) {
            return $this->nothingWaiting($connectionFlow);
        }

        $connectionFlow->forgetSignUp();
        $connectionFlow->awaitLogin($pending['identity'], email: null, confirm: true);

        return to_route('login');
    }

    public function cancel(Request $request, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $connectionFlow = new ConnectionFlow($request->session());
        $pending = $connectionFlow->pendingSignUp();

        $connectionFlow->forgetSignUp();

        if ($pending !== null) {
            $dispatchesCommands->dispatch(new WithdrawConnectionAccessCommand($pending['identity']));
        }

        return to_route('login');
    }

    /**
     * Nothing waits to finish. When the link went out a moment ago, the rider sent the form again from the
     * browser's history, and is told the link is on its way. Otherwise the sign-up ran out.
     */
    private function nothingWaiting(ConnectionFlow $connectionFlow): RedirectResponse
    {
        $sentTo = $connectionFlow->sentSignUpTo();

        if ($sentTo !== null) {
            return to_route('login')->with('status', 'A link is already on its way to '.$sentTo.'. Check your email.');
        }

        return to_route('login')->withErrors(['connection' => 'That took too long. Choose the account again to sign up.']);
    }
}
