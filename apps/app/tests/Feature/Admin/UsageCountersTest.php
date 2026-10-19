<?php

declare(strict_types=1);

use App\Account\Role;
use App\Contracts\Usage\UsageCounter;
use App\Domain\Usage\UsageMetric;
use App\Http\Middleware\NoteLastSeen;
use App\Mcp\Servers\TracewayServer;
use App\Mcp\Tools\ListRoutesTool;
use App\Mcp\Tools\PlanLoopTool;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;

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

it('counts a rider who only uses a watch or an AI assistant as active', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 10:07:00');
    $user = User::factory()->create();
    $token = 'device-token';
    DB::table('device_links')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'provider' => 'garmin',
        'label' => 'Edge',
        'token_hash' => hash('sha256', $token),
        'linked_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->withToken($token)->getJson(route('device.routes'))->assertOk();

    expect($user->fresh()?->last_seen_at?->toDateTimeString())->toBe('2026-10-19 10:00:00');

    CarbonImmutable::setTestNow();
});

it("notes an AI assistant's rider as active on every assistant request", function (): void {
    expect(Route::getRoutes()->getByName('mcp')?->gatherMiddleware())->toContain(NoteLastSeen::class);
});

it('counts each AI assistant tool call that worked, by tool and day, and nothing about who', function (): void {
    $user = User::factory()->create();

    TracewayServer::actingAs($user)->tool(ListRoutesTool::class, [])->assertOk();
    TracewayServer::actingAs($user)->tool(ListRoutesTool::class, [])->assertOk();
    TracewayServer::actingAs($user)->tool(PlanLoopTool::class, [])->assertHasErrors();

    expect(usageTotal(UsageMetric::McpListRoutes))->toBe(2)
        ->and(usageTotal(UsageMetric::McpPlanLoop))->toBe(0)
        ->and(DB::table('usage_counters')->where('metric', 'like', 'mcp_%')->pluck('metric')->all())
        ->toBe(['mcp_list_routes']);
});

it('answers the tool call when the count cannot be written', function (): void {
    $this->app->instance(UsageCounter::class, new class implements UsageCounter
    {
        public function add(string $metric, DateTimeImmutable $at, int $by = 1): void
        {
            throw new RuntimeException('The database is locked.');
        }

        public function set(string $metric, DateTimeImmutable $day, int $value): void {}

        public function ridersSeenSince(DateTimeImmutable $since): int
        {
            return 0;
        }

        public function forgetBefore(DateTimeImmutable $day): int
        {
            return 0;
        }
    });

    TracewayServer::actingAs(User::factory()->create())->tool(ListRoutesTool::class, [])->assertOk();
});

it('has a daily total for every AI assistant tool', function (): void {
    /** @var list<class-string<Tool>> $tools */
    $tools = (fn (): array => $this->tools)->call(new TracewayServer(new FakeTransporter));

    expect($tools)->not->toBeEmpty();

    foreach ($tools as $tool) {
        expect(UsageMetric::forTool(resolve($tool)->name()))->toBeInstanceOf(UsageMetric::class);
    }
});

it("shows the AI assistant tools in the overview's usage table", function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    DB::table('usage_counters')->insert(['day' => now()->utc()->toDateString(), 'metric' => 'mcp_plan_loop', 'count' => 3]);

    $this->actingAs($admin)
        ->get(route('admin.overview'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('overview.usage', fn (Collection $usage): bool => $usage->contains(
                fn (array $metric): bool => $metric['metric'] === 'mcp_plan_loop' && $metric['label'] === 'Assistant tool: plan a loop' && $metric['total'] === 3,
            )));
});
