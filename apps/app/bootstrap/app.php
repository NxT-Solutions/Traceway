<?php

declare(strict_types=1);

use App\Account\MissingRider;
use App\Http\Middleware\ForgetDomainCookies;
use App\Http\Middleware\GuardImpersonation;
use App\Http\Middleware\GuardSignupForms;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoteLastSeen;
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
        $middleware->trustProxies(at: '*');

        // Outermost, so error pages and the maintenance page get the headers too.
        $middleware->prepend(SendSecurityHeaders::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Outside EncryptCookies, so the old cookies are cleared as they are.
        $middleware->web(prepend: [ForgetDomainCookies::class]);

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

        // A rider who was deleted while an admin page was still open is simply not there any more.
        $exceptions->map(MissingRider::class, fn (MissingRider $missingRider): NotFoundHttpException => new NotFoundHttpException($missingRider->getMessage(), $missingRider));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
