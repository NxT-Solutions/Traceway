<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The session cookie used to name the domain. Since it is a __Host- cookie,
 * the browser could hold an old XSRF-TOKEN for the domain next to the new
 * one, send the old one first, and every form would come back expired. The
 * first visit with the old session cookie clears both old cookies.
 */
class ForgetDomainCookies
{
    private const string OLD_SESSION = 'traceway-session';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $session = config('session.cookie');

        if (! is_string($session) || ! str_starts_with($session, '__Host-') || ! $request->cookies->has(self::OLD_SESSION)) {
            return $response;
        }

        foreach ([self::OLD_SESSION, 'XSRF-TOKEN'] as $name) {
            $response->headers->clearCookie($name, '/', $request->getHost(), secure: true);
        }

        return $response;
    }
}
