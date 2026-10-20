<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Account\ConnectionProvider;
use App\Auth\Connections\AppleCallbackTicket;
use App\Contracts\Auth\IdentityProviders;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sign in with Apple posts its answer from Apple's site. This route runs outside the web middleware, so it opens
 * no session (which would replace the rider's own cookie) and asks for no CSRF token (Apple has none). It only
 * holds the answer and sends the browser on to the usual callback, where the session's state is checked. A post
 * that Apple could not have sent is not held, and the callback says it took too long.
 */
class AppleCallbackController extends Controller
{
    public function __invoke(Request $request, AppleCallbackTicket $appleCallbackTicket, IdentityProviders $identityProviders): RedirectResponse
    {
        abort_unless($identityProviders->isEnabled(ConnectionProvider::Apple), 404);

        $ticket = $appleCallbackTicket->hold($request->only(['code', 'state', 'user', 'error']));

        $query = $ticket === null ? ['provider' => 'apple'] : ['provider' => 'apple', 'ticket' => $ticket];

        return redirect()->to(route('connections.callback', $query), 303);
    }
}
