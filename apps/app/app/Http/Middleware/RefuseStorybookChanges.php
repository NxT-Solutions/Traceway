<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storybook is public at /storybook on this same site, so its stories run with a signed-in rider's cookies. A story's
 * requests are answered by mocks in the browser, but one the mocks miss must never change the rider's account: a
 * request that changes something and comes from a Storybook page is refused. Reading a page is still allowed.
 */
class RefuseStorybookChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            ! $request->isMethodSafe() && $this->fromStorybook($request),
            Response::HTTP_FORBIDDEN,
            'Storybook shows the planner with example data. It cannot change your account.',
        );

        /** @var Response */
        return $next($request);
    }

    /**
     * Whether the page that sent the request is one of Storybook's on this site. Storybook's pages send their full
     * address to this site (Referrer-Policy), so the path is there to check.
     */
    private function fromStorybook(Request $request): bool
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer)) {
            return false;
        }

        $host = parse_url($referer, PHP_URL_HOST);
        $path = parse_url($referer, PHP_URL_PATH);

        return $host === $request->getHost()
            && is_string($path)
            && str_starts_with($path, '/storybook/');
    }
}
