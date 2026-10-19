<?php

declare(strict_types=1);

use App\Account\Permission as PermissionName;
use App\Account\Role as RoleName;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Creates the roles and their permissions, makes every rider so far a User, and makes the listed addresses that
     * are already confirmed a Super Admin.
     */
    public function up(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            Role::findOrCreate($roleName->value, 'web')->syncPermissions(array_map(
                static fn (PermissionName $permissionName): string => $permissionName->value,
                $roleName->permissions(),
            ));
        }

        $morphClass = new User()->getMorphClass();
        $userRole = Role::findByName(RoleName::User->value, 'web');

        DB::table('model_has_roles')->insertUsing(
            ['role_id', 'model_type', 'model_id'],
            DB::table('users')
                ->selectRaw('?, ?, id', [$userRole->getKey(), $morphClass])
                ->whereNotIn('id', DB::table('model_has_roles')->where('model_type', $morphClass)->select('model_id')),
        );

        /** @var list<string> $superAdmins */
        $superAdmins = config('admin.super_admins', []);

        $listed = DB::table('users')
            ->whereNotNull('email_verified_at')
            ->whereIn(DB::raw('lower(email)'), $superAdmins)
            ->pluck('id')
            ->all();

        foreach (User::query()->whereKey($listed)->get() as $user) {
            $user->syncRoles([RoleName::SuperAdmin->value]);
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('model_has_roles')->delete();
        DB::table('role_has_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
