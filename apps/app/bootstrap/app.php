<?php

declare(strict_types=1);

use App\Account\MissingRider;
use App\Domain\Routing\MapServerBusy;
use App\Http\Middleware\ForgetDomainCookies;
use App\Http\Middleware\GuardImpersonation;
use App\Http\Middleware\GuardSignupForms;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoteLastSeen;
use App\Http\Middleware\RefuseStorybookChanges;
use App\Http\Middleware\SendSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare, then Caddy, sit in front, and Caddy passes on the host it answered for. Laravel takes only the
        // visitor's address and https from them. A forwarded host or port is never used: Caddy sets neither, so only
        // a visitor could send one through Cloudflare, and a link in an email would then lead to their site.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        // Only the host in APP_URL is answered. Laravel leaves this check out in local development and in tests.
        $middleware->trustHosts();

        // Outermost, so error pages and the maintenance page get the headers too.
        $middleware->prepend(SendSecurityHeaders::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Outside EncryptCookies, so the old cookies are cleared as they are. A change sent from a Storybook page is
        // refused before anything else reads the request.
        $middleware->web(prepend: [ForgetDomainCookies::class, RefuseStorybookChanges::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            GuardSignupForms::class,
            NoteLastSeen::class,
            GuardImpersonation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // The public map server is down for a moment now and then. The rider is told to try again, and there is nothing to fix.
        $exceptions->dontReport(MapServerBusy::class);

        // A rider who was deleted while an admin page was still open is simply not there any more.
        $exceptions->map(MissingRider::class, fn (MissingRider $missingRider): NotFoundHttpException => new NotFoundHttpException($missingRider->getMessage(), $missingRider));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
