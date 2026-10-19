<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The browser's safety rules for every answer Laravel gives. nginx sends the
 * same rules for the content site and the files it serves from disk, in
 * .docker/production/images/configs/nginx/security-headers.conf.
 *
 * Each page gets a fresh nonce, so only the scripts Traceway wrote run. A page
 * may reach Traceway, the OpenFreeMap map, and Cloudflare's sign-up check, and
 * nothing else, as the privacy policy promises.
 */
class SendSecurityHeaders
{
    /**
     * The pages a chat app's sign-in window passes through. They keep their
     * link to the window that opened them, which the app may wait on.
     */
    private const array SIGN_IN_WINDOW = ['oauth/*', 'login', 'two-factor-challenge', 'register'];

    private const string PERMISSIONS = 'accelerometer=(), camera=(), geolocation=(self), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (! str_starts_with((string) $headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $headers->set('Permissions-Policy', self::PERMISSIONS);

        // Laravel's own error page for developers runs inline scripts.
        if (config('app.debug') === true && $response->getStatusCode() >= 500) {
            return $response;
        }

        $headers->set('Content-Security-Policy', $this->policy($request));

        // A shared route's embed is made to sit in a frame on another site.
        if ($request->routeIs('routes.share.embed')) {
            return $response;
        }

        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (! $request->is(...self::SIGN_IN_WINDOW)) {
            $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        }

        return $response;
    }

    private function policy(Request $request): string
    {
        $directives = [
            'default-src' => ["'none'"],
            'script-src' => ["'self'", "'nonce-".Vite::cspNonce()."'", 'https://challenges.cloudflare.com'],
            // Radix and Sonner add their own style tags, which carry no nonce.
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", 'https://tiles.openfreemap.org'],
            'worker-src' => ["'self'", 'blob:'],
            'frame-src' => ['https://challenges.cloudflare.com'],
            'manifest-src' => ["'self'"],
            'media-src' => ["'self'"],
            'base-uri' => ["'none'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        if ($request->routeIs('routes.share.embed')) {
            $directives['frame-ancestors'] = ['*'];
        }

        // Approving a chat app sends the browser on to that app's own address.
        if ($request->is('oauth/*')) {
            unset($directives['form-action']);
        }

        // The API reference is Scalar's page, loaded from its CDN. Its bundle
        // calls eval, and the page shows nothing a visitor typed.
        if ($request->is('docs')) {
            $directives['script-src'][] = 'https://cdn.jsdelivr.net';
            $directives['script-src'][] = "'unsafe-eval'";
            $directives['style-src'][] = 'https://fonts.scalar.com';
            $directives['font-src'][] = 'https://fonts.scalar.com';
        }

        if (Vite::isRunningHot()) {
            $directives = $this->allowViteDevServer($directives);
        }

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->values();

        if ($request->isSecure()) {
            $policy->push('upgrade-insecure-requests');
        }

        return $policy->implode('; ');
    }

    /**
     * While `bun run dev` runs, the scripts, styles and live reload come from Vite.
     *
     * @param  array<string, list<string>>  $directives
     * @return array<string, list<string>>
     */
    private function allowViteDevServer(array $directives): array
    {
        $hot = parse_url(trim((string) file_get_contents(Vite::hotFile())));

        if (! is_array($hot) || ! isset($hot['scheme'], $hot['host'])) {
            return $directives;
        }

        $origin = $hot['host'].(isset($hot['port']) ? ':'.$hot['port'] : '');
        $secure = $hot['scheme'] === 'https';

        foreach (['script-src', 'style-src', 'img-src', 'font-src', 'connect-src'] as $directive) {
            $directives[$directive][] = ($secure ? 'https://' : 'http://').$origin;
        }

        $directives['connect-src'][] = ($secure ? 'wss://' : 'ws://').$origin;

        return $directives;
    }
}
