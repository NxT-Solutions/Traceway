<?php

declare(strict_types=1);

use App\Commands\ReadRouteDetailsCommand;
use App\Commands\SyncReleasesCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Domain\Releases\ReleaseFeed;
use App\Integrations\ReleaseNotes\ReleaseNotesDirectory;
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

Artisan::command('releases:sync', function (DispatchesCommands $dispatchesCommands, ReleaseFeed $releaseFeed): int {
    try {
        $saved = $dispatchesCommands->dispatch(new SyncReleasesCommand);
    } catch (Throwable $throwable) {
        report($throwable);
        $this->warn('The rider notes were not read: '.$throwable->getMessage());

        return 0;
    }

    $this->info('Read the rider notes of '.(is_int($saved) ? $saved : 0).' releases.');

    $unreleased = $releaseFeed->unreleased();

    if ($unreleased !== []) {
        $this->warn('Rider notes not in a release yet: '.implode(', ', $unreleased).'. They go out with the next release.');
    }

    return 0;
})->purpose('Read the rider notes in resources/releases, so riders see what changed');

Artisan::command('releases:prepare {version : The new version, such as v1.4.0} {--title= : The title riders see. Empty names the release by its first change} {--summary= : One line riders see under the title}', function (ReleaseNotesDirectory $releaseNotesDirectory): int {
    $version = is_string($this->argument('version')) ? $this->argument('version') : '';
    $title = $this->option('title');
    $summary = $this->option('summary');

    try {
        $moved = $releaseNotesDirectory->prepare(
            $version,
            is_string($title) ? $title : '',
            is_string($summary) && $summary !== '' ? $summary : null,
        );
    } catch (InvalidArgumentException $invalidArgumentException) {
        $this->error($invalidArgumentException->getMessage());

        return 1;
    }

    $this->info('Moved '.count($moved).' rider notes into '.$version.': '.implode(', ', $moved).'.');

    return 0;
})->purpose('Move the unreleased rider notes into the folder of a new version');
