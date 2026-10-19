<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a Super Admin acts as a rider, the rider's sign-in and the admin area stay closed: no password, two-factor,
 * passkeys, deleting the account, or emailing a copy of the data, and no admin pages until they stop.
 */
final readonly class GuardImpersonation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Impersonation::active($request) || ! $this->closed($request)) {
            return $next($request);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => 'You are acting as this rider. Stop first, then do that as yourself.']);

        return $request->isMethod('GET') ? to_route('dashboard') : back();
    }

    private function closed(Request $request): bool
    {
        if ($request->is('admin/impersonation')) {
            return false;
        }

        return $request->is('admin', 'admin/*', 'user/*', 'passkeys/*', 'settings/security', 'settings/password', 'settings/privacy/export')
            || ($request->is('settings/profile') && ! $request->isMethod('GET'));
    }
}
