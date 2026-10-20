<?php

declare(strict_types=1);

namespace App\Auth\Connections;

use App\Account\ConnectionProvider;
use App\Account\RiderId;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Logs a rider in once a connected service has said who they are, the way Fortify does after a password: a rider
 * with two-factor sign-in still answers the challenge, the session gets a new id, and the rider goes on to the
 * page they asked for, which may be an AI assistant's approval page.
 */
final readonly class ConnectionSignIn
{
    public function logIn(Request $request, RiderId $riderId, ConnectionProvider $connectionProvider, bool $remember): RedirectResponse
    {
        $user = Auth::guard('web')->getProvider()->retrieveById($riderId->value);

        abort_unless($user instanceof User, 404);

        new ConnectionFlow($request->session())->signingInWith($connectionProvider);

        if ($this->challenges($user)) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => $remember]);

            event(new TwoFactorAuthenticationChallenged($user));

            return to_route('two-factor.login');
        }

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login'));
    }

    /**
     * The same test Fortify uses before a password sign-in.
     */
    private function challenges(User $user): bool
    {
        if (! Features::enabled(Features::twoFactorAuthentication())) {
            return false;
        }

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm') && $user->two_factor_confirmed_at === null) {
            return false;
        }

        return $user->two_factor_secret !== null && in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), strict: true);
    }
}
