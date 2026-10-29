<?php

declare(strict_types=1);

use App\Account\AdminAction;
use App\Account\AdminRules;
use App\Account\RiderId;
use App\Account\Role;
use App\Commands\IdentifyConnectionsCommand;
use App\Commands\PostStatusNoticeCommand;
use App\Commands\ReadRouteDetailsCommand;
use App\Commands\RecordServiceChecksCommand;
use App\Commands\RecordTrafficSnapshotCommand;
use App\Commands\RecordUsageSnapshotCommand;
use App\Commands\ResolveStatusNoticeCommand;
use App\Commands\SyncReleasesCommand;
use App\Contracts\Account\AdminLog;
use App\Contracts\Account\UserRepository;
use App\Contracts\Bus\DispatchesCommands;
use App\Domain\Connections\ConnectionTally;
use App\Domain\Releases\ReleaseFeed;
use App\Domain\Routing\MapServerBusy;
use App\Domain\Status\StatusComponent;
use App\Integrations\ReleaseNotes\ReleaseNotesDirectory;
use App\Queries\Routes\UnreadRoutesQuery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        } catch (MapServerBusy) {
            // The rest wait for a later run, when riders have the map server's share.
            $this->warn('The map server is busy. The rest wait for the next run.');

            break;
        } catch (Throwable $throwable) {
            $this->warn('Route '.$route->routeId->value.' was not read: '.$throwable->getMessage());
        }
    }

    $this->info('Read the climb, turns, and stops of '.$filled.' of '.count($routes).' routes.');

    return 0;
})->purpose('Read the climb, surfaces, turns, and stops of routes saved before Traceway kept them');

// Production's scheduler reads the rest a few at a time: routes saved
// before Traceway kept details, and multisport legs a save left for later.
// A deploy needs nothing run by hand. The map servers are shared, and
// riders share Traceway's minute of the public one, so it goes slowly, and
// one run never starts while another is still going.
Schedule::command('routes:details', ['--limit' => 5])
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

Artisan::command('status:check', function (DispatchesCommands $dispatchesCommands): int {
    $saved = $dispatchesCommands->dispatch(new RecordServiceChecksCommand);

    $this->info('Checked '.(is_int($saved) ? $saved : 0).' parts of Traceway.');

    return 0;
})->purpose('Ask Traceway and the services it uses whether they work, for the status page');

// The status page shows the last of these, and the worst state of each day.
// A check that waits for a slow server must not hold up the next one.
Schedule::command('status:check')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Artisan::command('status:notice {title : What riders read, such as "Route drawing is slow"} {--body= : One or two more sentences} {--component=* : The parts it is about: planner, routing, places, tiles, ride_with_gps, wahoo, garmin_store}', function (DispatchesCommands $dispatchesCommands): int {
    $components = [];
    $option = $this->option('component');

    foreach (is_array($option) ? $option : [] as $value) {
        $statusComponent = is_string($value) ? StatusComponent::tryFrom($value) : null;

        if (! $statusComponent instanceof StatusComponent) {
            $this->error('There is no part called '.(is_string($value) ? $value : '').'. Use one of: '.implode(', ', array_map(fn (StatusComponent $statusComponent): string => $statusComponent->value, StatusComponent::cases())).'.');

            return 1;
        }

        $components[] = $statusComponent;
    }

    $title = $this->argument('title');
    $body = $this->option('body');

    try {
        $id = $dispatchesCommands->dispatch(new PostStatusNoticeCommand(
            is_string($title) ? $title : '',
            is_string($body) ? $body : null,
            $components,
        ));
    } catch (InvalidArgumentException $invalidArgumentException) {
        $this->error($invalidArgumentException->getMessage());

        return 1;
    }

    $this->info('Posted notice '.(is_int($id) ? $id : 0).'. It shows on the status page and on the map until you run status:resolve.');

    return 0;
})->purpose('Tell riders about a problem on the status page and on the map');

Artisan::command('status:resolve {id? : The notice to close. Empty closes the newest open one}', function (DispatchesCommands $dispatchesCommands): int {
    $id = $this->argument('id');
    $resolved = $dispatchesCommands->dispatch(new ResolveStatusNoticeCommand(is_numeric($id) ? (int) $id : null));

    if (! is_int($resolved)) {
        $this->warn('No open notice to close.');

        return 0;
    }

    $this->info('Closed notice '.$resolved.'. The status page shows it as resolved for a week.');

    return 0;
})->purpose('Close a notice once the problem is over');

Artisan::command("users:role {email : The rider's sign-in email} {role : super-admin, admin, or user}", function (UserRepository $userRepository, AdminLog $adminLog): int {
    $email = $this->argument('email');
    $role = Role::tryFrom(is_string($this->argument('role')) ? $this->argument('role') : '');

    if (! $role instanceof Role) {
        $this->error('There is no such role. Use one of: '.implode(', ', array_map(fn (Role $role): string => $role->value, Role::cases())).'.');

        return 1;
    }

    $id = DB::table('users')->whereRaw('lower(email) = ?', [mb_strtolower(is_string($email) ? $email : '')])->value('id');

    if (! is_numeric($id)) {
        $this->error('No rider signs in with that email.');

        return 1;
    }

    $riderId = new RiderId((int) $id);

    if (! AdminRules::keepsASuperAdmin($userRepository->role($riderId), $role, $userRepository->countWithRole(Role::SuperAdmin))) {
        $this->error('This is the last Super Admin. Make someone else a Super Admin first.');

        return 1;
    }

    $before = $userRepository->role($riderId);
    $userRepository->assignRole($riderId, $role);
    $adminLog->record(actorId: null, adminAction: AdminAction::RoleChanged, riderId: $riderId, details: ['from' => $before->value, 'to' => $role->value]);
    $this->info('The rider is now '.$role->label().'.');

    return 0;
})->purpose('Give a rider a role: super-admin, admin, or user');

Artisan::command('usage:snapshot', function (DispatchesCommands $dispatchesCommands): int {
    $forgotten = $dispatchesCommands->dispatch(new RecordUsageSnapshotCommand);

    $this->info('Wrote the active riders for today and forgot '.(is_int($forgotten) ? $forgotten : 0).' old totals.');

    return 0;
})->purpose('Write how many riders were active, for the admin area, and forget old usage totals');

// Shortly before midnight, so each day's gauge covers that whole day.
Schedule::command('usage:snapshot')->dailyAt('23:55');

Artisan::command('traffic:snapshot', function (DispatchesCommands $dispatchesCommands): int {
    $saved = $dispatchesCommands->dispatch(new RecordTrafficSnapshotCommand);

    $this->info('Kept the Web Analytics totals of '.(is_int($saved) ? $saved : 0).' more days.');

    return 0;
})->purpose('Keep the Web Analytics totals of each finished day, for the Traffic page after Cloudflare drops them');

// Cloudflare answers a day in full for a week only. Each run asks for the
// finished days not kept yet, so a missed or half answered run is caught
// up on by the next one.
Schedule::command('traffic:snapshot')->hourlyAt(20)->withoutOverlapping(10);

// The activity log of the admin area is kept for a year.
Artisan::command('admin:prune-log', function (AdminLog $adminLog): int {
    $deleted = $adminLog->forgetBefore(now()->subYear()->toDateTimeImmutable());

    $this->info('Forgot '.$deleted.' entries of the admin activity log.');

    return 0;
})->purpose('Forget admin activity older than a year');

Schedule::command('admin:prune-log')->dailyAt('03:10');

Artisan::command('connections:identify', function (DispatchesCommands $dispatchesCommands): int {
    $tally = $dispatchesCommands->dispatch(new IdentifyConnectionsCommand);
    $tally = $tally instanceof ConnectionTally ? $tally : new ConnectionTally;

    $counts = 'Found the account of '.$tally->done.' of '.$tally->total().' Wahoo and Ride with GPS connections from before. '
        .$tally->refused.' need connecting again, '.$tally->unreachable.' could not be asked, and '.$tally->skipped.' belong to another rider.';

    // The scheduler throws a command's output away, so the counts go to the log too.
    $this->info($counts);
    Log::info($counts);

    return 0;
})->purpose('Ask Wahoo and Ride with GPS whose account each connection from before is, so it logs the rider in');

// A connection from before that Wahoo or Ride with GPS could not answer for at
// deploy is asked again each night, so it does not wait for the next deploy.
// A slow service must not hold up the status checks either.
Schedule::command('connections:identify')
    ->dailyAt('04:40')
    ->withoutOverlapping(30)
    ->runInBackground();
