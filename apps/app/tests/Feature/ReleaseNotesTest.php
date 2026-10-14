<?php

declare(strict_types=1);

use App\Domain\Releases\Release as PublishedRelease;
use App\Domain\Releases\ReleaseFeed;
use App\Models\Release;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @param  list<PublishedRelease>  $releases
 * @param  list<string>  $unreleased
 */
function releaseFeed(array $releases, bool $broken = false, array $unreleased = []): ReleaseFeed
{
    return new readonly class($releases, $broken, $unreleased) implements ReleaseFeed
    {
        /**
         * @param  list<PublishedRelease>  $releases
         * @param  list<string>  $unreleased
         */
        public function __construct(private array $releases, private bool $broken, private array $unreleased) {}

        public function published(): array
        {
            throw_if($this->broken, InvalidArgumentException::class, 'v0.2.0/_release.md needs a title.');

            return $this->releases;
        }

        public function unreleased(): array
        {
            return $this->unreleased;
        }
    };
}

function publishedRelease(string $tag, string $notes, ?string $date = null, ?string $summary = null): PublishedRelease
{
    return new PublishedRelease($tag, 'Title of '.$tag, $summary, $notes, $date === null ? null : CarbonImmutable::parse($date));
}

test('the sync saves the rider notes of each release and brings an edited one up to date', function (): void {
    $this->app->instance(ReleaseFeed::class, releaseFeed([
        publishedRelease('v0.2.0', "## New\n\n* Water stops", '2026-10-10T10:00:00Z', 'Stops on the way.'),
        publishedRelease('v0.1.0', "## New\n\n* Loops", '2026-10-01T10:00:00Z'),
    ]));

    $this->artisan('releases:sync')->expectsOutput('Read the rider notes of 2 releases.')->assertSuccessful();

    $this->app->instance(ReleaseFeed::class, releaseFeed([
        publishedRelease('v0.2.0', "## New\n\n* Water and toilet stops", '2026-10-10T10:00:00Z', 'Stops on the way.'),
        publishedRelease('v0.1.0', "## New\n\n* Loops", '2026-10-01T10:00:00Z'),
    ]));

    $this->artisan('releases:sync')->assertSuccessful();

    $release = Release::query()->where('tag', 'v0.2.0')->firstOrFail();

    expect(Release::query()->count())->toBe(2)
        ->and($release->notes)->toBe("## New\n\n* Water and toilet stops")
        ->and($release->name)->toBe('Title of v0.2.0')
        ->and($release->summary)->toBe('Stops on the way.');
});

test('a release with no date gets the time a deploy first reads it, and keeps it', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20T08:00:00Z'));
    $this->app->instance(ReleaseFeed::class, releaseFeed([publishedRelease('v0.8.0', "## New\n\n* Rivers")]));
    $this->artisan('releases:sync')->assertSuccessful();

    $this->travelTo(CarbonImmutable::parse('2026-10-22T08:00:00Z'));
    $this->artisan('releases:sync')->assertSuccessful();

    expect(Release::query()->where('tag', 'v0.8.0')->firstOrFail()->published_at->toIso8601String())->toBe('2026-10-20T08:00:00+00:00');
});

test('the sync reports a broken notes file and does not fail the deploy', function (): void {
    $this->app->instance(ReleaseFeed::class, releaseFeed([], broken: true));

    $this->artisan('releases:sync')
        ->expectsOutputToContain('The rider notes were not read: v0.2.0/_release.md needs a title.')
        ->assertSuccessful();
});

test('the sync warns about rider notes merged but not released', function (): void {
    $this->app->instance(ReleaseFeed::class, releaseFeed([publishedRelease('v0.1.0', "## New\n\n* Loops")], unreleased: ['river-routes.md']));

    $this->artisan('releases:sync')
        ->expectsOutputToContain('Rider notes not in a release yet: river-routes.md.')
        ->assertSuccessful();
});

test('the header counts the releases a rider has not read', function (): void {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    Release::factory()->create(['tag' => 'v0.1.0', 'published_at' => now()->subWeek()]);
    Release::factory()->create(['tag' => 'v0.2.0', 'name' => 'Stops', 'notes' => "## New\n\n* Water stops", 'published_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('whatsNew.unread', 2)
            ->where('whatsNew.items.0.tag', 'v0.2.0')
            ->where('whatsNew.items.0.name', 'Stops')
            ->where('whatsNew.items.0.headline', 'Water stops'));
});

test('a new rider does not get the whole history as unread', function (): void {
    $user = User::factory()->create(['created_at' => now()]);
    Release::factory()->create(['published_at' => now()->subYear()]);
    Release::factory()->create(['published_at' => now()->subWeek()]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('whatsNew.unread', 1));
});

test('a guest gets no release notes', function (): void {
    Release::factory()->create();

    $this->get(route('login'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('whatsNew', expected: null));
});

test('a rider can mark one release as read, and it stays unread for everyone else', function (): void {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    $other = User::factory()->create(['created_at' => now()->subYear()]);
    $read = Release::factory()->create();
    Release::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('releases.read', $read->id))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('whatsNew.unread', 1));
    $this->actingAs($other)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('whatsNew.unread', 2));
});

test('marking a release read twice keeps one read', function (): void {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    $this->actingAs($user)->post(route('releases.read', $release->id))->assertRedirect();
    $this->actingAs($user)->post(route('releases.read', $release->id))->assertRedirect();

    $this->assertDatabaseCount('release_reads', 1);
});

test('marking an unknown release read is not found', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('releases.read', 999))
        ->assertNotFound();
});

test('a rider can mark every release as read', function (): void {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    Release::factory()->count(3)->create();

    $this->actingAs($user)->post(route('releases.read-all'))->assertRedirect();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('whatsNew.unread', 0)
            ->where('whatsNew.items', []));
});

test("the What's new page shows the notes, and which ones are read", function (): void {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    $read = Release::factory()->create(['tag' => 'v0.1.0', 'published_at' => now()->subWeek()]);
    Release::factory()->create(['tag' => 'v0.2.0', 'notes' => "## New\n\n* Water stops", 'published_at' => now()->subDay()]);
    $this->actingAs($user)->post(route('releases.read', $read->id));

    $this->actingAs($user)
        ->get(route('releases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('settings/whats-new')
            ->has('releases', 2)
            ->where('releases.0.tag', 'v0.2.0')
            ->where('releases.0.read', expected: false)
            ->where('releases.0.html', "<h3>New</h3>\n<ul>\n<li>Water stops</li>\n</ul>\n")
            ->where('releases.1.read', expected: true));
});

test('markup in release notes is shown as text, not run', function (): void {
    $user = User::factory()->create();
    Release::factory()->create(['notes' => "## New\n\n* <script>alert(1)</script> and [a link](javascript:alert)"]);

    $this->actingAs($user)
        ->get(route('releases.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('releases.0.html', fn (string $html): bool => ! str_contains($html, '<script>')
            && str_contains($html, '&lt;script&gt;')
            && ! str_contains($html, 'href="javascript')));
});

test('a guest is sent to sign in', function (): void {
    $release = Release::factory()->create();

    $this->get(route('releases.index'))->assertRedirect(route('login'));
    $this->post(route('releases.read', $release->id))->assertRedirect(route('login'));
    $this->post(route('releases.read-all'))->assertRedirect(route('login'));
});

test('the sync forgets a release whose notes were removed', function (): void {
    $this->app->instance(ReleaseFeed::class, releaseFeed([
        publishedRelease('v0.3.0', "## New\n\n* Three", '2026-10-12T10:00:00Z'),
        publishedRelease('v0.2.0', "## New\n\n* Two", '2026-10-10T10:00:00Z'),
    ]));
    $this->artisan('releases:sync')->assertSuccessful();

    $this->app->instance(ReleaseFeed::class, releaseFeed([
        publishedRelease('v0.2.0', "## New\n\n* Two", '2026-10-10T10:00:00Z'),
    ]));
    $this->artisan('releases:sync')->assertSuccessful();

    expect(Release::query()->pluck('tag')->all())->toBe(['v0.2.0']);
});

test('a release summary is the line the bell shows, and the page shows it above the notes', function (): void {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    Release::factory()->create(['summary' => 'Stops show up again.', 'notes' => "## Fixes\n\n* Water stops"]);

    $this->actingAs($user)->get(route('releases.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('whatsNew.items.0.headline', 'Stops show up again.')
            ->where('releases.0.summary', 'Stops show up again.'));
});

test('a rider hears of releases from the month before joining, and none older', function (): void {
    $user = User::factory()->create(['created_at' => now()->subDays(10)]);
    Release::factory()->create(['tag' => 'v0.3.0', 'published_at' => now()->subDays(39)]);
    Release::factory()->create(['tag' => 'v0.1.0', 'published_at' => now()->subDays(41)]);

    $this->actingAs($user)->get(route('releases.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('releases', 1)
            ->where('releases.0.tag', 'v0.3.0')
            ->where('whatsNew.unread', 1));
});

test('marking every release read leaves out the ones the rider was never told about', function (): void {
    $user = User::factory()->create(['created_at' => now()->subDays(10)]);
    Release::factory()->create(['published_at' => now()->subDay()]);
    Release::factory()->create(['published_at' => now()->subYear()]);

    $this->actingAs($user)->post(route('releases.read-all'))->assertRedirect();

    $this->assertDatabaseCount('release_reads', 1);
});

test('an image in release notes shows as its description, and loads nothing', function (): void {
    $user = User::factory()->create();
    Release::factory()->create(['notes' => "## New\n\n* A new map ![the new map](https://tracker.example/pixel.png)"]);

    $this->actingAs($user)
        ->get(route('releases.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('releases.0.html', fn (string $html): bool => ! str_contains($html, '<img')
            && ! str_contains($html, 'tracker.example')
            && str_contains($html, 'A new map the new map')));
});
