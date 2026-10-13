<?php

declare(strict_types=1);

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\Sport;
use App\Queries\RowReader;
use App\Routes\SavedLeg;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('routes:details {--limit=100 : How many routes to read in this run}', function (RouteDetailsSource $routeDetailsSource): int {
    $limitOption = $this->option('limit');
    $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : 100;
    $rows = DB::table('saved_routes')
        ->whereNull('details')->oldest()
        ->limit($limit)
        ->get(['id', 'sport', 'coordinates', 'legs']);
    $filled = 0;

    foreach ($rows as $row) {
        $rowReader = new RowReader($row);
        $legs = $rowReader->legs('legs');
        $unread = array_filter($legs, fn (SavedLeg $savedLeg): bool => $savedLeg->sport instanceof Sport && ! $savedLeg->details instanceof RouteDetails);

        // A multisport plan keeps its details on its legs, so its own column stays empty.
        if ($legs !== [] && $unread === []) {
            continue;
        }

        try {
            if ($legs === []) {
                $routeDetails = $routeDetailsSource->for(Sport::from($rowReader->string('sport')), $rowReader->line('coordinates'));
                DB::table('saved_routes')->where('id', $rowReader->string('id'))->update([
                    'details' => json_encode($routeDetails->toArray(), JSON_THROW_ON_ERROR),
                    'ascent_metres' => $routeDetails->ascentMetres,
                ]);
            } else {
                $read = array_map(
                    fn (SavedLeg $savedLeg): SavedLeg => $savedLeg->sport instanceof Sport && ! $savedLeg->details instanceof RouteDetails
                        ? $savedLeg->withDetails($routeDetailsSource->for($savedLeg->sport, $savedLeg->coordinates))
                        : $savedLeg,
                    $legs,
                );
                DB::table('saved_routes')->where('id', $rowReader->string('id'))->update([
                    'legs' => json_encode(array_map(fn (SavedLeg $savedLeg): array => $savedLeg->toStored(), $read), JSON_THROW_ON_ERROR),
                    'ascent_metres' => array_sum(array_map(fn (SavedLeg $savedLeg): int => $savedLeg->ascentMetres, $read)),
                ]);
            }

            $filled++;
        } catch (Throwable $throwable) {
            $this->warn('Route '.$rowReader->string('id').' was not read: '.$throwable->getMessage());
        }
    }

    $this->info('Read the climb, turns, and stops of '.$filled.' of '.count($rows).' routes.');

    return 0;
})->purpose('Read the climb, surfaces, turns, and stops of routes saved before Traceway kept them');
