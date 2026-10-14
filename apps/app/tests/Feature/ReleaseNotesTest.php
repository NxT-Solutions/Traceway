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
 */
function releaseFeed(array $releases, bool $down = false): ReleaseFeed
{
    return new readonly class($releases, $down) implements ReleaseFeed
    {
        /**
         * @param  list<PublishedRelease>  $releases
         */
        public function __construct(private array $releases, private bool $down) {}

        public function published(): array
        {
            throw_if($this->down, RuntimeException::class, 'GitHub did not list the releases.');

            return $this->releases;
        }
    };
}

test('the sync copies the published releases and brings an edited one up to date', function (): void {
    $this->app->instance(ReleaseFeed::class, releaseFeed([
        new PublishedRelease('v0.2.0', 'Stops along the way', CarbonImmutable::parse('2026-10-10T10:00:00Z'), "## New\n\n* Water stops"),
        new PublishedRelease('v0.1.0', 'First release', CarbonImmutable::parse('2026-10-01T10:00:00Z'), "## New\n\n* Loops"),
    ]));

    $this->artisan('releases:sync')->expectsOutput('Read the notes of 2 releases.')->assertSuccessful();

    $this->app->instance(ReleaseFeed::class, releaseFeed([
        new PublishedRelease('v0.2.0', 'Stops along the way', CarbonImmutable::parse('2026-10-10T10:00:00Z'), "## New\n\n* Water and toilet stops"),
    ]));

    $this->artisan('releases:sync')->assertSuccessful();

    expect(Release::query()->count())->toBe(2)
        ->and(Release::query()->where('tag', 'v0.2.0')->value('notes'))->toBe("## New\n\n* Water and toilet stops");
});

test('the sync warns and does not fail when GitHub is down', function (): void {
    $this->app->instance(ReleaseFeed::class, releaseFeed([], down: true));

    $this->artisan('releases:sync')
        ->expectsOutputToContain('The releases were not read from GitHub')
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

test('the sync forgets a release GitHub no longer lists', function (): void {
    $this->app->instance(ReleaseFeed::class, releaseFeed([
        new PublishedRelease('v0.3.0', 'Three', CarbonImmutable::parse('2026-10-12T10:00:00Z'), "## New\n\n* Three"),
        new PublishedRelease('v0.2.0', 'Two', CarbonImmutable::parse('2026-10-10T10:00:00Z'), "## New\n\n* Two"),
    ]));
    $this->artisan('releases:sync')->assertSuccessful();
    Release::factory()->create(['tag' => 'v0.1.0', 'published_at' => CarbonImmutable::parse('2026-09-01T10:00:00Z')]);

    $this->app->instance(ReleaseFeed::class, releaseFeed([
        new PublishedRelease('v0.2.0', 'Two', CarbonImmutable::parse('2026-10-10T10:00:00Z'), "## New\n\n* Two"),
    ]));
    $this->artisan('releases:sync')->assertSuccessful();

    expect(Release::query()->orderBy('tag')->pluck('tag')->all())->toBe(['v0.1.0', 'v0.2.0']);
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
