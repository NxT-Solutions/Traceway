<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\LastSignIn;
use App\Auth\Impersonation;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;

/**
 * Remembers on this browser how the rider logged in, so the log in page can mark it "Last used" next time.
 */
final readonly class RememberSignInMethod
{
    public function handle(Login $login): void
    {
        $request = request();

        // A Super Admin starting to act as a rider, or a demo rider, is not the rider choosing how to log in.
        if ($login->guard !== 'web' || Impersonation::active($request) || $request->routeIs('login.demo')) {
            return;
        }

        $method = match (true) {
            $request->routeIs('passkey.login') => LastSignIn::PASSKEY,
            $request->routeIs('login.store', 'register.store') => LastSignIn::PASSWORD,
            default => new ConnectionFlow($request->session())->pullSigningInWith()->value
                ?? ($request->routeIs('two-factor.login.store') ? LastSignIn::PASSWORD : null),
        };

        if ($method !== null) {
            Cookie::queue(LastSignIn::cookie($method));
        }
    }
}
