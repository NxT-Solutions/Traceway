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
}
