<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Queries\Account\RouteServicesQuery;
use App\Queries\Releases\ReleaseNotesQuery;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Override;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    #[Override]
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            // What changed in Traceway since the rider last looked, for the bell in the header.
            'whatsNew' => fn (): ?array => $user instanceof User
                ? resolve(ReleaseNotesQuery::class)->summaryFor($user->id, $user->created_at)
                : null,
            // The services this rider can send a route to from Send to device, and whether each is connected.
            'routeServices' => fn (): array => $user instanceof User
                ? resolve(RouteServicesQuery::class)->forRider($user->id)
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
