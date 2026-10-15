<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Account\RiderId;
use App\Contracts\Routes\RouteImageStore;
use App\Domain\Routing\LineSimplifier;
use App\Domain\Routing\Sport;
use App\Queries\RowReader;
use App\Routes\RouteId;
use App\Routes\SavedLeg;
use Illuminate\Support\Facades\DB;

final readonly class LibraryQuery
{
    public function __construct(private RouteImageStore $routeImageStore) {}

    public function page(int $riderId): LibraryPage
    {
        return new LibraryPage($this->forRider($riderId));
    }

    /**
     * @return list<LibraryRoute>
     */
    public function forRider(int $riderId): array
    {
        $rows = DB::table('saved_routes')
            ->where('user_id', $riderId)->latest()
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'sport',
                'surface',
                'elevation',
                'roads',
                'layout',
                'distance_metres',
                'ascent_metres',
                'created_at',
                'coordinates',
                'legs',
            ]);

        $sent = $this->sentTo($riderId);
        $routes = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $id = $reader->string('id');
            $routes[] = new LibraryRoute(
                new RouteId($id),
                $reader->string('name'),
                $reader->string('sport'),
                $reader->string('surface'),
                $reader->string('elevation'),
                $reader->string('roads'),
                $reader->int('distance_metres'),
                $reader->int('ascent_metres'),
                SignedRouteLink::share($id),
                SignedRouteLink::fit($id),
                $reader->iso('created_at'),
                // The library draws thumbnails and an overview map, so the
                // line is thinned. The share page and its files keep it all.
                LineSimplifier::simplify($reader->line('coordinates'), LineSimplifier::OVERVIEW_METRES),
                $reader->optionalString('layout'),
                $this->legs($id, $reader->legs('legs')),
                $this->routeImageStore->drawnAt(new RiderId($riderId), new RouteId($id)) !== null,
                SignedRouteLink::gpx($id),
                $sent[$id] ?? [],
            );
        }

        return $routes;
    }

    /**
     * The services each route went to, by route id.
     *
     * @return array<string, list<string>>
     */
    private function sentTo(int $riderId): array
    {
        $rows = DB::table('sent_routes')
            ->where('user_id', $riderId)
            ->distinct()
            ->orderBy('service')
            ->get(['saved_route_id', 'service']);

        $sent = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $sent[$reader->string('saved_route_id')][] = $reader->string('service');
        }

        return $sent;
    }

    /**
     * @param  list<SavedLeg>  $savedLegs
     * @return list<array{discipline: string, distance_metres: int, fit_url: string|null, gpx_url: string|null}>
     */
    private function legs(string $id, array $savedLegs): array
    {
        $legs = [];

        foreach ($savedLegs as $index => $savedLeg) {
            $legs[] = [
                'discipline' => $savedLeg->discipline(),
                'distance_metres' => $savedLeg->distanceMetres,
                'fit_url' => $savedLeg->sport instanceof Sport ? SignedRouteLink::fitLeg($id, $index + 1) : null,
                'gpx_url' => $savedLeg->sport instanceof Sport ? SignedRouteLink::gpxLeg($id, $index + 1) : null,
            ];
        }

        return $legs;
    }
}
