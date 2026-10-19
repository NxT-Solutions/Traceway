<?php

declare(strict_types=1);

use App\Domain\Usage\UsageMetric;
use App\Http\Middleware\NoteLastSeen;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function usageTotal(UsageMetric $usageMetric, ?string $day = null): int
{
    $count = DB::table('usage_counters')
        ->where('metric', $usageMetric->value)
        ->where('day', $day ?? now()->utc()->toDateString())
        ->value('count');

    return is_numeric($count) ? (int) $count : 0;
}

it('keeps no column that could say who did something', function (): void {
    expect(Schema::getColumnListing('usage_counters'))->toBe(['day', 'metric', 'count']);
});

it("adds a sign-in to today's total", function (): void {
    $user = User::factory()->create(['password' => 'a-long-password-1']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-long-password-1']);
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-long-password-1']);

    expect(usageTotal(UsageMetric::SignIns))->toBe(2);
});

it('counts a request that worked and skips one that failed', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('routes.places'), [])->assertUnprocessable();

    expect(usageTotal(UsageMetric::PlaceSearches))->toBe(0);
});

it('notes when a rider was last seen, at most every quarter hour', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 10:00:00');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    expect($user->fresh()?->last_seen_at?->toDateTimeString())->toBe('2026-10-19 10:00:00');

    CarbonImmutable::setTestNow('2026-10-19 10:05:00');
    $this->actingAs($user)->get(route('profile.edit'));
    expect($user->fresh()?->last_seen_at?->toDateTimeString())->toBe('2026-10-19 10:00:00');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-19 10:00:00')->addSeconds(NoteLastSeen::EVERY_SECONDS));
    $this->actingAs($user)->get(route('profile.edit'));
    expect($user->fresh()?->last_seen_at?->toDateTimeString())->toBe('2026-10-19 10:15:00');

    CarbonImmutable::setTestNow();
});

it('writes the active riders each day and forgets totals older than two years', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 23:55:00');
    User::factory()->create(['last_seen_at' => now()->subHours(3)]);
    User::factory()->create(['last_seen_at' => now()->subDays(5)]);
    User::factory()->create(['last_seen_at' => now()->subDays(20)]);
    User::factory()->create(['last_seen_at' => null]);
    DB::table('usage_counters')->insert(['day' => '2024-01-01', 'metric' => 'route_previews', 'count' => 9]);

    Artisan::call('usage:snapshot');

    expect(usageTotal(UsageMetric::ActiveRidersDay))->toBe(1)
        ->and(usageTotal(UsageMetric::ActiveRidersWeek))->toBe(2)
        ->and(usageTotal(UsageMetric::ActiveRidersMonth))->toBe(3)
        ->and(DB::table('usage_counters')->where('day', '2024-01-01')->count())->toBe(0);

    CarbonImmutable::setTestNow();
});
