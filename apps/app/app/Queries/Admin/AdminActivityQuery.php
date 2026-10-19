<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Account\AdminAction;
use App\Account\Role;
use App\Queries\RowReader;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The activity log of the admin area, newest first: who did what to whose account.
 *
 * @phpstan-type Person array{id: int, name: string}
 * @phpstan-type Entry array{id: int, action: string, summary: string, at: string|null, actor: Person|null, rider: Person|null}
 * @phpstan-type Page array{entries: list<Entry>, page: int, pages: int, total: int}
 */
final readonly class AdminActivityQuery
{
    public const int PER_PAGE = 30;

    /**
     * @return Page
     */
    public function page(int $page): array
    {
        $total = DB::table('admin_actions')->count();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);

        return [
            'entries' => $this->entries($this->rows()->forPage($page, self::PER_PAGE)),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    /**
     * The latest entries about one rider, for their page.
     *
     * @return list<Entry>
     */
    public function forRider(int $riderId, int $limit = 10): array
    {
        return $this->entries($this->rows()->where('admin_actions.rider_id', $riderId)->limit($limit));
    }

    private function rows(): Builder
    {
        return DB::table('admin_actions')
            ->leftJoin('users as actors', 'actors.id', '=', 'admin_actions.actor_id')
            ->leftJoin('users as riders', 'riders.id', '=', 'admin_actions.rider_id')
            ->select([
                'admin_actions.id',
                'admin_actions.action',
                'admin_actions.details',
                'admin_actions.created_at',
                'admin_actions.actor_id',
                'actors.name as actor_name',
                'admin_actions.rider_id',
                'riders.name as rider_name',
            ])
            ->latest('admin_actions.created_at')
            ->orderByDesc('admin_actions.id');
    }

    /**
     * @return list<Entry>
     */
    private function entries(Builder $builder): array
    {
        $entries = [];

        foreach ($builder->get() as $row) {
            $reader = new RowReader($row);
            $actorId = $reader->optionalString('actor_id');
            $riderId = $reader->optionalString('rider_id');

            $entries[] = [
                'id' => $reader->int('id'),
                'action' => $reader->string('action'),
                'summary' => $this->summary($reader->string('action'), $reader->optionalString('details')),
                'at' => $reader->iso('created_at'),
                'actor' => $actorId === null ? null : ['id' => (int) $actorId, 'name' => $reader->optionalString('actor_name') ?? ''],
                'rider' => $riderId === null ? null : ['id' => (int) $riderId, 'name' => $reader->optionalString('rider_name') ?? ''],
            ];
        }

        return $entries;
    }

    private function summary(string $action, ?string $details): string
    {
        $adminAction = AdminAction::tryFrom($action);
        $label = $adminAction instanceof AdminAction ? $adminAction->label() : $action;
        $decoded = $details === null ? null : json_decode($details, associative: true);

        if ($adminAction === AdminAction::RoleChanged && is_array($decoded)) {
            $from = Role::tryFrom(is_string($decoded['from'] ?? null) ? $decoded['from'] : '');
            $to = Role::tryFrom(is_string($decoded['to'] ?? null) ? $decoded['to'] : '');

            if ($from instanceof Role && $to instanceof Role) {
                return sprintf('Changed the role from %s to %s', $from->label(), $to->label());
            }
        }

        return $label;
    }
}
