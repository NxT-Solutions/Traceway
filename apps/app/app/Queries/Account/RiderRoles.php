<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\Role;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The role each rider holds, read from the permission tables. Only riders hold roles in Traceway, so a row's
 * model is always a rider. A rider with several roles holds the strongest.
 */
final readonly class RiderRoles
{
    public function of(int $riderId): Role
    {
        return $this->forRiders([$riderId])[$riderId] ?? Role::User;
    }

    /**
     * @param  list<int>  $riderIds
     * @return array<int, Role>
     */
    public function forRiders(array $riderIds): array
    {
        if ($riderIds === []) {
            return [];
        }

        $names = [];

        foreach ($this->rows()->whereIn('model_has_roles.model_id', $riderIds)->get(['model_has_roles.model_id', 'roles.name']) as $row) {
            if (is_numeric($row->model_id) && is_string($row->name)) {
                $names[(int) $row->model_id][] = $row->name;
            }
        }

        $roles = [];

        foreach ($riderIds as $riderId) {
            $roles[$riderId] = Role::strongest($names[$riderId] ?? []);
        }

        return $roles;
    }

    /**
     * The ids of the riders who hold this role, for a filter.
     */
    public function idsWith(Role $role): Builder
    {
        return $this->rows()->where('roles.name', $role->value)->select('model_has_roles.model_id');
    }

    private function rows(): Builder
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id');
    }
}
