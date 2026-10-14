<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Sport;
use App\Queries\RowReader;
use App\Routes\RouteId;
use App\Routes\SavedLeg;
use App\Routes\UnreadRoute;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class UnreadRoutesQuery
{
    /**
     * A route the map servers could not read waits this long before the
     * next try, so a run is not spent on it again and again.
     */
    private const int RETRY_HOURS = 6;

    /**
     * The routes to read next: never tried first, then the longest since
     * the last try, so a route the map servers keep failing on cannot
     * hold the others back. A multisport plan keeps its details on its
     * legs, so it is done when every land leg has them.
     *
     * @return list<UnreadRoute>
     */
    public function next(int $limit): array
    {
        $routes = [];
        $lazyCollection = DB::table('saved_routes')
            ->whereNull('details')
            ->where(fn (Builder $builder): Builder => $builder
                ->whereNull('details_tried_at')
                ->orWhere('details_tried_at', '<', now()->subHours(self::RETRY_HOURS)))
            ->orderByRaw('details_tried_at is not null')
            ->oldest('details_tried_at')
            ->orderBy('id')
            ->select(['id', 'sport', 'coordinates', 'legs'])
            ->cursor();

        foreach ($lazyCollection as $row) {
            $rowReader = new RowReader($row);
            $legs = $rowReader->legs('legs');
            $unread = array_filter($legs, fn (SavedLeg $savedLeg): bool => $savedLeg->sport instanceof Sport && ! $savedLeg->details instanceof RouteDetails);
            $sport = Sport::tryFrom($rowReader->string('sport'));

            if (($legs !== [] && $unread === []) || ! $sport instanceof Sport) {
                continue;
            }

            $routes[] = new UnreadRoute(new RouteId($rowReader->string('id')), $sport, $rowReader->line('coordinates'), $legs);

            if (count($routes) >= $limit) {
                break;
            }
        }

        return $routes;
    }
}
