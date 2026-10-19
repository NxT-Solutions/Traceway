<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Account\Permission;
use App\Auth\Impersonation;
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
     * Pages the server does not draw first. Their first render reads the
     * browser: the planner and the library restore the rider's draft and
     * layout from local storage and the screen width, and the settings and
     * admin pages write dates in the browser's language and time zone. Drawn
     * on the server, they would not match and React would draw them again.
     *
     * @var array<int, string>
     */
    #[Override]
    protected $withoutSsr = [
        'dashboard',
        'saved',
        'settings',
        'settings/*',
        'admin',
        'admin/*',
    ];

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
                // What the admin area lets this person do, for the Admin link and the buttons on its pages.
                // While acting as a rider, the admin area is closed, so nothing offers it.
                'can' => $user instanceof User && ! Impersonation::active($request) ? $this->abilities($user) : null,
            ],
            // The bar that says whose Traceway this is, and stops acting as them.
            'impersonating' => $user instanceof User && Impersonation::active($request) ? ['name' => $user->name] : null,
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

    /**
     * @return array{admin: bool, viewAnalytics: bool, viewUsers: bool, manageUsers: bool, assignRoles: bool}
     */
    private function abilities(User $user): array
    {
        $viewAnalytics = $user->can(Permission::ViewAnalytics->value);
        $viewUsers = $user->can(Permission::ViewUsers->value);

        // The checks load the role and permission relations. The page needs only the answers.
        $user->makeHidden(['roles', 'permissions']);

        return [
            'admin' => $viewAnalytics || $viewUsers,
            'viewAnalytics' => $viewAnalytics,
            'viewUsers' => $viewUsers,
            'manageUsers' => $user->can(Permission::ManageUsers->value),
            'assignRoles' => $user->can(Permission::AssignRoles->value),
        ];
    }
}
