<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Account\ConnectionFailed;
use App\Account\ConnectionProvider;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\ConnectionIntent;
use App\Contracts\Auth\IdentityProviders;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Account\ConnectionsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends the rider to a service's own sign-in page, to log in or to prove it is them. A full-page GET, so the
 * browser follows the redirect off the site and the page's form-action rule does not get in the way.
 */
class StartConnectionController extends Controller
{
    public function signIn(Request $request, string $provider, IdentityProviders $identityProviders): RedirectResponse
    {
        $connectionProvider = $this->enabled($provider, $identityProviders);

        new ConnectionFlow($request->session())->start(ConnectionIntent::SignIn, $connectionProvider, remember: $request->boolean('remember'));

        try {
            return redirect()->away($identityProviders->redirectUrl($connectionProvider));
        } catch (ConnectionFailed $connectionFailed) {
            return to_route('login')->withErrors(['connection' => $connectionFailed->getMessage()]);
        }
    }

    /**
     * For a rider without a password: the service vouches for them before a sensitive step, as a password or a
     * passkey would. Only a service already connected to their account can. A rider with a password gives it,
     * since a service often lets anyone at an open browser through with one click.
     */
    public function confirm(Request $request, string $provider, IdentityProviders $identityProviders, ConnectionsQuery $connectionsQuery): RedirectResponse
    {
        $connectionProvider = $this->enabled($provider, $identityProviders);
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_if($user->password !== null, 404);
        abort_if($connectionsQuery->identityOf($user->id, $connectionProvider) === null, 404);

        new ConnectionFlow($request->session())->start(ConnectionIntent::Confirm, $connectionProvider, $user->id);

        try {
            return redirect()->away($identityProviders->redirectUrl($connectionProvider));
        } catch (ConnectionFailed $connectionFailed) {
            return to_route('password.confirm')->withErrors(['connection' => $connectionFailed->getMessage()]);
        }
    }

    private function enabled(string $provider, IdentityProviders $identityProviders): ConnectionProvider
    {
        $connectionProvider = ConnectionProvider::tryFrom($provider);

        abort_unless($connectionProvider instanceof ConnectionProvider && $identityProviders->isEnabled($connectionProvider), 404);

        return $connectionProvider;
    }
}
