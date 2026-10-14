<?php

declare(strict_types=1);

use App\Commands\ReadRouteDetailsCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Queries\Routes\UnreadRoutesQuery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('routes:details {--limit=100 : How many routes to read in this run}', function (UnreadRoutesQuery $unreadRoutesQuery, DispatchesCommands $dispatchesCommands): int {
    $limitOption = $this->option('limit');
    $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : 100;
    $routes = $unreadRoutesQuery->next($limit);
    $filled = 0;

    foreach ($routes as $route) {
        try {
            if ($dispatchesCommands->dispatch(new ReadRouteDetailsCommand($route)) === true) {
                $filled++;
            }
        } catch (Throwable $throwable) {
            $this->warn('Route '.$route->routeId->value.' was not read: '.$throwable->getMessage());
        }
    }

    $this->info('Read the climb, turns, and stops of '.$filled.' of '.count($routes).' routes.');

    return 0;
})->purpose('Read the climb, surfaces, turns, and stops of routes saved before Traceway kept them');

// Production's scheduler reads the rest a few at a time: routes saved
// before Traceway kept details, and multisport legs a save left for later.
// A deploy needs nothing run by hand. The map servers are shared, so it
// goes slowly, and one run never starts while another is still going.
Schedule::command('routes:details', ['--limit' => 20])
    ->everyTenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

// The database cache drops an expired entry only when the same key is read
// again. A route a rider drew and never saved, or a place name, would stay
// in the cache table, and in every backup, with no end.
Artisan::command('cache:prune-expired', function (): int {
    $connection = config('cache.stores.database.connection');
    $table = config('cache.stores.database.table');
    $deleted = DB::connection(is_string($connection) ? $connection : null)
        ->table(is_string($table) ? $table : 'cache')
        ->where('expiration', '<=', now()->getTimestamp())
        ->delete();

    $this->info('Deleted '.$deleted.' expired cache entries.');

    return 0;
})->purpose('Delete expired entries from the database cache');

Schedule::command('cache:prune-expired')->hourly();

// A message that could not be sent keeps its content, a data export
// included, in failed_jobs. A day is long enough to see why.
Schedule::command('queue:prune-failed', ['--hours' => 24])->hourly();
