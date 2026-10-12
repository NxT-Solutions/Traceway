<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Queries\RowReader;
use Illuminate\Support\Facades\DB;

final class SavedRouteRow
{
    public static function find(string $id): RowReader
    {
        $row = DB::table('saved_routes')->where('id', $id)->first();

        abort_if($row === null, 404);

        return new RowReader($row);
    }

    /**
     * The route only when the rider owns it. Another rider's route is a 404, so its id does not leak.
     */
    public static function findOwned(string $id, int $riderId): RowReader
    {
        $row = DB::table('saved_routes')->where('id', $id)->where('user_id', $riderId)->first();

        abort_if($row === null, 404);

        return new RowReader($row);
    }
}
