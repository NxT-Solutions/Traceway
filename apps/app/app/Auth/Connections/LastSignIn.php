<?php

declare(strict_types=1);

namespace App\Auth\Connections;

use App\Account\ConnectionProvider;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The way a rider last logged in on this browser, so the log in page can mark it "Last used". The cookie holds
 * only that word: password, passkey, or the connected service. It says nothing about who the rider is.
 */
final class LastSignIn
{
    public const string COOKIE = 'last_sign_in';

    public const string PASSWORD = 'password';

    public const string PASSKEY = 'passkey';

    /**
     * About 400 days, the longest a browser keeps a cookie.
     */
    private const int MINUTES = 576_000;

    public static function cookie(string $method): Cookie
    {
        return cookie(self::COOKIE, $method, self::MINUTES, '/', secure: config('session.secure') === true, httpOnly: true, raw: false, sameSite: 'lax');
    }

    /**
     * The method the cookie names, or null when there is none or it is not one Traceway knows.
     */
    public static function read(Request $request): ?string
    {
        $method = $request->cookie(self::COOKIE);

        return is_string($method) && self::known($method) ? $method : null;
    }

    public static function known(string $method): bool
    {
        return $method === self::PASSWORD || $method === self::PASSKEY || ConnectionProvider::tryFrom($method) instanceof ConnectionProvider;
    }
}
