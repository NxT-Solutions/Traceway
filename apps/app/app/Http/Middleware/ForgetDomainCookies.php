<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cookies used to name the domain. Since the session is a __Host- cookie,
 * new cookies are for traceway.online alone, and a browser can hold an old
 * domain copy next to a new one under the same name. It sends the old one
 * first, which PHP reads: every form would come back expired, and a renewed
 * "Remember me" would stop working. So the old domain copies are cleared.
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

        if (! is_string($session) || ! str_starts_with($session, '__Host-')) {
            return $response;
        }

        foreach ($this->oldCookies($request) as $name) {
            $response->headers->clearCookie($name, '/', $request->getHost(), secure: true);
        }

        return $response;
    }

    /**
     * The old session and its XSRF-TOKEN, and any cookie the browser sends twice.
     *
     * @return list<string>
     */
    private function oldCookies(Request $request): array
    {
        preg_match_all('/(?:^|;)\s*([^=;\s]+)=/', (string) $request->headers->get('Cookie'), $sent);

        $names = array_keys(array_filter(array_count_values($sent[1]), fn (int $count): bool => $count > 1));

        if ($request->cookies->has(self::OLD_SESSION)) {
            $names[] = self::OLD_SESSION;
            $names[] = 'XSRF-TOKEN';
        }

        return array_values(array_unique($names));
    }
}
