<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Queries\RowReader;
use App\Routes\RouteId;
use App\Routes\SavedLeg;
use Illuminate\Support\Facades\DB;

final class LibraryQuery
{
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
                $reader->iso('created_at'),
                $reader->line('coordinates'),
                $reader->optionalString('layout'),
                array_map(fn (SavedLeg $savedLeg): array => [
                    'discipline' => $savedLeg->discipline(),
                    'distance_metres' => $savedLeg->distanceMetres,
                ], $reader->legs('legs')),
            );
        }

        return $routes;
    }
}
