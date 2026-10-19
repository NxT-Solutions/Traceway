<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Account\Role;
use App\Queries\Account\RiderRoles;
use App\Queries\RowReader;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The list of riders in the admin area, newest first, a page at a time.
 *
 * @phpstan-type Row array{id: int, name: string, email: string, role: string, verified: bool, two_factor: bool, routes: int, created_at: string|null, last_seen_at: string|null}
 * @phpstan-type Page array{users: list<Row>, page: int, pages: int, total: int, per_page: int}
 */
final readonly class AdminUsersQuery
{
    public const int PER_PAGE = 25;

    public function __construct(private RiderRoles $riderRoles) {}

    /**
     * @return Page
     */
    public function page(?string $search, ?Role $role, ?bool $verified, ?bool $twoFactor, int $page): array
    {
        $builder = DB::table('users')
            ->when($search !== null && $search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower((string) $search)).'%';
                $builder->where(fn (Builder $builder): Builder => $builder
                    ->whereRaw("lower(name) like ? escape '\\'", [$like])
                    ->orWhereRaw("lower(email) like ? escape '\\'", [$like]));
            })
            ->when($role instanceof Role, fn (Builder $builder): Builder => $builder->whereIn('id', $this->riderRoles->idsWith($role ?? Role::User)))
            ->when($verified !== null, fn (Builder $builder): Builder => $verified === true ? $builder->whereNotNull('email_verified_at') : $builder->whereNull('email_verified_at'))
            ->when($twoFactor !== null, fn (Builder $builder): Builder => $twoFactor === true ? $builder->whereNotNull('two_factor_confirmed_at') : $builder->whereNull('two_factor_confirmed_at'));

        $total = (clone $builder)->count();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);

        $rows = $builder
            ->select(['id', 'name', 'email', 'email_verified_at', 'two_factor_confirmed_at', 'created_at', 'last_seen_at'])
            ->selectSub(DB::table('saved_routes')->whereColumn('saved_routes.user_id', 'users.id')->selectRaw('count(*)'), 'routes')->latest()
            ->orderByDesc('id')
            ->forPage($page, self::PER_PAGE)
            ->get();

        $readers = array_values($rows->map(fn (object $row): RowReader => new RowReader($row))->all());
        $roles = $this->riderRoles->forRiders(array_map(fn (RowReader $rowReader): int => $rowReader->int('id'), $readers));

        return [
            'users' => array_map(fn (RowReader $rowReader): array => [
                'id' => $rowReader->int('id'),
                'name' => $rowReader->string('name'),
                'email' => $rowReader->string('email'),
                'role' => ($roles[$rowReader->int('id')] ?? Role::User)->value,
                'verified' => $rowReader->optionalString('email_verified_at') !== null,
                'two_factor' => $rowReader->optionalString('two_factor_confirmed_at') !== null,
                'routes' => $rowReader->int('routes'),
                'created_at' => $rowReader->iso('created_at'),
                'last_seen_at' => $rowReader->iso('last_seen_at'),
            ], $readers),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => self::PER_PAGE,
        ];
    }
}
