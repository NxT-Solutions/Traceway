<?php

declare(strict_types=1);

use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceProbe;
use App\Domain\Status\ServiceProbes;
use App\Domain\Status\ServiceState;
use App\Domain\Status\StatusComponent;
use App\Models\User;
use App\Queries\Status\PlannerProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @param  array<string, ServiceState>  $states  by component
 */
function fakeProbes(array $states): ServiceProbes
{
    return new ServiceProbes(array_map(
        fn (string $component, ServiceState $serviceState): ServiceProbe => new readonly class(StatusComponent::from($component), $serviceState) implements ServiceProbe
        {
            public function __construct(private StatusComponent $statusComponent, private ServiceState $serviceState) {}

            public function component(): StatusComponent
            {
                return $this->statusComponent;
            }

            public function check(): ProbeResult
            {
                return new ProbeResult($this->serviceState, 120);
            }
        },
        array_keys($states),
        array_values($states),
    ));
}

/**
 * @return array<string, ServiceState>
 */
function allUp(): array
{
    return [
        'planner' => ServiceState::Up,
        'routing' => ServiceState::Up,
        'places' => ServiceState::Up,
        'tiles' => ServiceState::Up,
        'garmin_store' => ServiceState::Waiting,
    ];
}

test('a check saves one answer per part and forgets checks over a year old', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    DB::table('status_checks')->insert(['component' => 'routing', 'state' => 'up', 'latency_ms' => 90, 'checked_at' => now()->subDays(400)]);
    DB::table('status_checks')->insert(['component' => 'routing', 'state' => 'down', 'latency_ms' => 90, 'checked_at' => now()->subDays(200)]);

    $this->artisan('status:check')->expectsOutput('Checked 5 parts of Traceway.')->assertSuccessful();

    expect(DB::table('status_checks')->orderBy('id')->pluck('state', 'component')->count())->toBe(5)
        ->and(DB::table('status_checks')->where('checked_at', '<', now()->subDays(366))->count())->toBe(0)
        ->and(DB::table('status_checks')->where('state', 'down')->count())->toBe(1);
});

test('anyone can open the status page and see every part', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');

    $this->get('/status')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('status')
            ->where('state', 'up')
            ->where('headline', 'Everything works')
            ->where('stale', expected: false)
            ->where('groups.0.label', 'Traceway')
            ->where('groups.1.label', 'Map services')
            ->where('groups.1.components.0.label', 'Route drawing')
            ->where('groups.1.components.0.uptime', 100)
            ->where('range', 'day')
            ->has('groups.1.components.0.bars', 24)
            ->where('groups.2.components.0.state', 'waiting')
            ->where('groups.2.components.0.summary', 'The Garmin app is waiting for Garmin to approve it'));
});

test('a broken map service is the headline', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Down]));
    $this->artisan('status:check');

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('state', 'down')
        ->where('headline', 'Route drawing is down right now')
        ->where('groups.1.components.0.uptime', 0));
});

test('when the checks stop, the page says so and shows no old answers', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');
    $this->travel(20)->minutes();

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('state', 'unknown')
        ->where('stale', expected: true)
        ->where('headline', 'Checks have stopped, so this page may be out of date')
        ->where('groups.1.components.0.state', expected: null));
});

test('a notice shows until it is resolved, then stays a week as resolved', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');

    $this->artisan('status:notice', ['title' => 'Route drawing is slow', '--body' => 'The map server is busy.', '--component' => ['routing']])
        ->assertSuccessful();

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('state', 'slow')
        ->where('headline', 'Route drawing is slow')
        ->where('notices.0.title', 'Route drawing is slow')
        ->where('notices.0.components', ['routing'])
        ->where('notices.0.resolved_at', expected: null));

    $this->artisan('status:resolve')->expectsOutputToContain('Closed notice')->assertSuccessful();
    $this->artisan('status:resolve')->expectsOutput('No open notice to close.')->assertSuccessful();

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert->whereNot('notices.0.resolved_at', expected: null));

    $this->travel(8)->days();

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert->where('notices', []));
});

test('a notice needs a title and a known part', function (): void {
    $this->artisan('status:notice', ['title' => 'Slow', '--component' => ['weather']])->assertFailed();
    $this->artisan('status:notice', ['title' => '   '])->assertFailed();

    expect(DB::table('status_notices')->count())->toBe(0);
});

test('the map tells the rider when route drawing is down, and says nothing when all is well', function (): void {
    $user = User::factory()->create();

    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert->where('serviceNotice', expected: null));

    $this->travel(5)->minutes();
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Down]));
    $this->artisan('status:check');

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('serviceNotice.title', 'Route drawing is down right now.'));

    $this->artisan('status:notice', ['title' => 'We are moving to a new map server tonight']);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('serviceNotice.title', 'We are moving to a new map server tonight'));
});

test('the planner stops offering Draw only while route drawing is down', function (): void {
    $user = User::factory()->create();
    $routingDown = fn (): bool => (bool) $this->actingAs($user)->get('/dashboard')->viewData('page')['props']['routingDown'];

    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');

    expect($routingDown())->toBeFalse();

    // Slow still draws, and another service being down does not stop it.
    $this->travel(5)->minutes();
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Slow, 'places' => ServiceState::Down]));
    $this->artisan('status:check');

    expect($routingDown())->toBeFalse();

    $this->travel(5)->minutes();
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Down]));
    $this->artisan('status:check');

    expect($routingDown())->toBeTrue();

    // A check gone stale says nothing: Draw comes back rather than stay off.
    $this->travel(2)->hours();

    expect($routingDown())->toBeFalse();

    // An open notice about route drawing holds it off, until it is closed.
    $this->artisan('status:notice', ['title' => 'Moving the map server', '--component' => ['routing']]);

    expect($routingDown())->toBeTrue();

    $this->artisan('status:resolve');

    expect($routingDown())->toBeFalse();
});

test('the planner keeps Draw, and says routes are limited, while the backup draws', function (): void {
    $user = User::factory()->create();
    $routingDown = fn (): bool => (bool) $this->actingAs($user)->get('/dashboard')->viewData('page')['props']['routingDown'];

    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Down, 'routing_backup' => ServiceState::Up]));
    $this->artisan('status:check');

    expect($routingDown())->toBeFalse();
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('serviceNotice.title', 'Route drawing is limited right now.')
        ->where('serviceNotice.body', 'The map server is not answering, so a backup draws your routes. The climb and the turns may not load.'));

    // With the backup down too, nothing draws, and the planner says so.
    $this->travel(5)->minutes();
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Down, 'routing_backup' => ServiceState::Down]));
    $this->artisan('status:check');

    expect($routingDown())->toBeTrue();
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('serviceNotice.title', 'Route drawing is down right now.'));
});

test('a backup that is down alone does not make the status page red', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing_backup' => ServiceState::Down]));
    $this->artisan('status:check');

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('headline', 'Everything works')
        ->where('state', 'up'));
});

test('the checks run every five minutes', function (): void {
    $this->artisan('schedule:list')->expectsOutputToContain('status:check');
});

test('before the first check the page says there are none', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));

    $this->get('/status')->assertOk()->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('state', 'unknown')
        ->where('headline', 'No checks yet')
        ->where('groups.1.components.0.summary', 'Not checked yet'));
});

test('the page opens on the last 24 hours, an hour to a bar with the worst moment in it', function (): void {
    $this->travelTo('2026-10-29 10:20:00');
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Slow]));
    $this->artisan('status:check');
    $this->travel(5)->minutes();
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('range', 'day')
        ->where('groups.1.components.0.state', 'up')
        ->has('groups.1.components.0.bars', 24)
        ->where('groups.1.components.0.bars.23.start', '2026-10-29T10:00:00Z')
        ->where('groups.1.components.0.bars.23.state', 'slow')
        ->where('groups.1.components.0.bars.22.start', '2026-10-29T09:00:00Z')
        ->where('groups.1.components.0.bars.22.state', expected: null)
        ->where('groups.1.components.0.bars.0.start', '2026-10-28T11:00:00Z'));
});

test('a range shows its days or weeks, and its own uptime', function (): void {
    $this->travelTo('2026-10-29 10:20:00');
    DB::table('status_checks')->insert(['component' => 'routing', 'state' => 'down', 'latency_ms' => 90, 'checked_at' => '2026-09-19 08:00:00']);
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Slow]));
    $this->artisan('status:check');

    $this->get('/status?range=week')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('range', 'week')
        ->has('groups.1.components.0.bars', 7)
        ->where('groups.1.components.0.bars.6.start', '2026-10-29T00:00:00Z')
        ->where('groups.1.components.0.bars.6.state', 'slow')
        ->where('groups.1.components.0.uptime', 100));

    $this->get('/status?range=quarter')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('range', 'quarter')
        ->has('groups.1.components.0.bars', 90)
        ->where('groups.1.components.0.bars.49.start', '2026-09-19T00:00:00Z')
        ->where('groups.1.components.0.bars.49.state', 'down')
        ->where('groups.1.components.0.uptime', 50));

    $this->get('/status?range=year')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('range', 'year')
        ->has('groups.1.components.0.bars', 52)
        ->where('groups.1.components.0.bars.51.start', '2026-10-23T00:00:00Z')
        ->where('groups.1.components.0.bars.51.state', 'slow')
        ->where('groups.1.components.0.bars.46.state', 'down'));
});

test('an unknown range shows the last 24 hours', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');

    $this->get('/status?range=decade')->assertOk()->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('range', 'day')
        ->has('groups.1.components.0.bars', 24));
});

test('a notice about something else never hides a broken map service', function (): void {
    $user = User::factory()->create();
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Down]));
    $this->artisan('status:check');
    $this->artisan('status:notice', ['title' => 'Wahoo sends are paused', '--component' => ['wahoo']]);

    $this->get('/status')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('headline', 'Route drawing is down right now'));
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('serviceNotice.title', 'Route drawing is down right now.'));

    $this->travel(5)->minutes();
    $this->app->instance(ServiceProbes::class, fakeProbes(allUp()));
    $this->artisan('status:check');

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('serviceNotice.title', 'Wahoo sends are paused'));
});

test('the map says nothing about old checks once they have stopped', function (): void {
    $this->app->instance(ServiceProbes::class, fakeProbes([...allUp(), 'routing' => ServiceState::Slow]));
    $this->artisan('status:check');
    $this->travel(20)->minutes();

    $this->actingAs(User::factory()->create())->get('/dashboard')->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('serviceNotice', expected: null));
});

test('the planner is slow when a message waits in the queue too long', function (): void {
    expect(new PlannerProbe()->check()->state)->toBe(ServiceState::Up);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(11)->getTimestamp(),
        'created_at' => now()->subMinutes(11)->getTimestamp(),
    ]);

    expect(new PlannerProbe()->check()->state)->toBe(ServiceState::Slow);
});
