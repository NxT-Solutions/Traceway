<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Account\AdminRules;
use App\Account\Email;
use App\Account\Permission;
use App\Account\Rider;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Account\Role;
use App\Commands\AdminAssignRoleCommand;
use App\Commands\AdminCreateUserCommand;
use App\Commands\AdminDeleteUserCommand;
use App\Commands\AdminEndSessionsCommand;
use App\Commands\AdminResetTwoFactorCommand;
use App\Commands\AdminUpdateUserCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Queries\Account\RiderRoles;
use App\Queries\Admin\AdminActivityQuery;
use App\Queries\Admin\AdminUserQuery;
use App\Queries\Admin\AdminUsersQuery;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The riders in the admin area. Every write goes through a command whose handler checks the permission and the
 * admin rules again, so a refused write comes back as a message on the page, not an error.
 */
class AdminUsersController extends Controller
{
    public function __construct(private readonly DispatchesCommands $dispatchesCommands) {}

    public function index(Request $request, AdminUsersQuery $adminUsersQuery): Response
    {
        $search = $request->string('search')->trim()->limit(100, '')->toString();
        $role = Role::tryFrom($request->string('role')->toString());
        $verified = $this->flag($request, 'verified');
        $twoFactor = $this->flag($request, 'two_factor');

        return Inertia::render('admin/users/index', [
            'users' => $adminUsersQuery->page($search !== '' ? $search : null, $role, $verified, $twoFactor, $request->integer('page', 1)),
            'filters' => [
                'search' => $search,
                'role' => $role?->value,
                'verified' => $request->string('verified')->toString(),
                'two_factor' => $request->string('two_factor')->toString(),
            ],
            'roles' => $this->roles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/create');
    }

    public function store(StoreUserRequest $storeUserRequest): RedirectResponse
    {
        $rider = $this->dispatchesCommands->dispatch(new AdminCreateUserCommand(
            actorId: $this->actor($storeUserRequest),
            name: new RiderName($storeUserRequest->string('name')->toString()),
            email: new Email($storeUserRequest->string('email')->toString()),
        ));

        abort_unless($rider instanceof Rider, 500);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account opened. '.$rider->email->value.' got a link to choose a password.']);

        return to_route('admin.users.show', ['rider' => $rider->id->value]);
    }

    public function show(Request $request, int $rider, AdminUserQuery $adminUserQuery, RiderRoles $riderRoles, AdminActivityQuery $adminActivityQuery): Response
    {
        $user = $adminUserQuery->find($rider);

        abort_if($user === null, 404);

        $actor = $this->actor($request);
        $actorRole = $riderRoles->of($actor->value);
        $mayManage = AdminRules::mayManage($actorRole, Role::from($user['role']), $actor->value === $rider);
        $requester = $request->user();

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'activity' => $adminActivityQuery->forRider($rider),
            'roles' => $this->roles(),
            'can' => [
                'manage' => $mayManage && $requester instanceof User && $requester->can(Permission::ManageUsers->value),
                'assignRole' => $mayManage && $requester instanceof User && $requester->can(Permission::AssignRoles->value),
                'self' => $actor->value === $rider,
                'impersonate' => $requester instanceof User
                    && $requester->can(Permission::Impersonate->value)
                    && AdminRules::mayImpersonate($actorRole, Role::from($user['role']), $actor->value === $rider),
            ],
        ]);
    }

    public function update(UpdateUserRequest $updateUserRequest, int $rider): RedirectResponse
    {
        $this->attempt(fn (): mixed => $this->dispatchesCommands->dispatch(new AdminUpdateUserCommand(
            actorId: $this->actor($updateUserRequest),
            riderId: new RiderId($rider),
            name: new RiderName($updateUserRequest->string('name')->toString()),
            email: new Email($updateUserRequest->string('email')->toString()),
            confirmEmail: $updateUserRequest->boolean('confirm_email'),
        )), 'Account saved.');

        return back();
    }

    public function role(AssignRoleRequest $assignRoleRequest, int $rider): RedirectResponse
    {
        $role = Role::from($assignRoleRequest->string('role')->toString());

        $this->attempt(fn (): mixed => $this->dispatchesCommands->dispatch(new AdminAssignRoleCommand(
            actorId: $this->actor($assignRoleRequest),
            riderId: new RiderId($rider),
            role: $role,
        )), 'The rider is now '.$role->label().'.');

        return back();
    }

    public function resetTwoFactor(Request $request, int $rider): RedirectResponse
    {
        $this->attempt(fn (): mixed => $this->dispatchesCommands->dispatch(new AdminResetTwoFactorCommand(
            actorId: $this->actor($request),
            riderId: new RiderId($rider),
        )), 'Two-factor sign-in is off. The rider can turn it on again in Settings.');

        return back();
    }

    public function endSessions(Request $request, int $rider): RedirectResponse
    {
        $this->attempt(fn (): mixed => $this->dispatchesCommands->dispatch(new AdminEndSessionsCommand(
            actorId: $this->actor($request),
            riderId: new RiderId($rider),
        )), 'The rider is signed out everywhere.');

        return back();
    }

    public function destroy(Request $request, int $rider): RedirectResponse
    {
        $deleted = $this->attempt(fn (): mixed => $this->dispatchesCommands->dispatch(new AdminDeleteUserCommand(
            actorId: $this->actor($request),
            riderId: new RiderId($rider),
        )), 'Account deleted.');

        return $deleted ? to_route('admin.users.index') : back();
    }

    /**
     * Runs the write and leaves a message for the page. A refusal is shown, not thrown.
     */
    private function attempt(Closure $write, string $done): bool
    {
        try {
            $write();
        } catch (AuthorizationException $authorizationException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $authorizationException->getMessage()]);

            return false;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $done]);

        return true;
    }

    private function actor(Request $request): RiderId
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return new RiderId($user->id);
    }

    /**
     * Whether a yes-or-no filter is set: "yes", "no", or nothing.
     */
    private function flag(Request $request, string $key): ?bool
    {
        return match ($request->string($key)->toString()) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roles(): array
    {
        return array_map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()], Role::cases());
    }
}
