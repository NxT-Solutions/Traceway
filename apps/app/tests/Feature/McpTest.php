<?php

declare(strict_types=1);

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\Place;
use App\Domain\Routing\PlaceFinder;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Mcp\Apps\RouteMapApp;
use App\Mcp\Servers\TracewayServer;
use App\Mcp\Tools\ListRoutesTool;
use App\Mcp\Tools\PlanLoopTool;
use App\Mcp\Tools\PlanMultisportTool;
use App\Mcp\Tools\RouteDrawingTool;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Mcp\Server\Testing\TestResponse;

uses(RefreshDatabase::class);

function mcpEngine(bool $fails = false): RoutingEngine
{
    return new readonly class($fails) implements RoutingEngine
    {
        public function __construct(private bool $fails) {}

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            return $this->candidates($routeQuery)[0];
        }

        /**
         * @return list<GeneratedRoute>
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            throw_if($this->fails, RuntimeException::class, 'Valhalla is down.');

            $start = [$routeQuery->startLongitude, $routeQuery->startLatitude];

            return [new GeneratedRoute($routeQuery, [$start, [4.95, 51.12], [4.97, 51.08], $start], $routeQuery->distanceMetres + 400, 0)];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, $waypoints, 6_000, 0);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, [$from, $to], 1_000, 0);
        }
    };
}

function mcpPlaces(): PlaceFinder
{
    return new class implements PlaceFinder
    {
        /** @var list<string> */
        public array $searches = [];

        public function find(string $search): ?Place
        {
            $this->searches[] = $search;

            return match ($search) {
                'Westerlo, Belgium' => new Place('Westerlo, Antwerp, Belgium', 51.09, 4.92),
                'Bergom' => new Place('Bergom, Westerlo, Belgium', 51.08, 4.89),
                default => null,
            };
        }
    };
}

beforeEach(function (): void {
    $this->app->instance(RoutingEngine::class, mcpEngine());
    $this->app->instance(PlaceFinder::class, mcpPlaces());
});

it('plans a loop from a place name and saves it to the library', function (): void {
    $user = User::factory()->create();

    TracewayServer::actingAs($user)
        ->tool(PlanLoopTool::class, [
            'start' => 'Westerlo, Belgium',
            'sport' => 'road',
            'distance_km' => 40,
            'via' => 'Bergom',
        ])
        ->assertOk()
        ->assertSee('Saved “40.4 km road ride from Westerlo”')
        ->assertSee('/share/');

    $savedRoute = SavedRoute::query()->sole();

    expect($savedRoute->user_id)->toBe($user->id)
        ->and($savedRoute->sport)->toBe('road')
        ->and($savedRoute->surface)->toBe('paved')
        ->and($savedRoute->roads)->toBe('quiet')
        ->and($savedRoute->distance_metres)->toBe(40_400)
        ->and($savedRoute->coordinates[0])->toBe([4.92, 51.09]);
});

it('takes a start as latitude and longitude without a place search', function (): void {
    TracewayServer::actingAs(User::factory()->create())
        ->tool(PlanLoopTool::class, [
            'start' => '51.05, 3.72',
            'sport' => 'trail_run',
            'distance_km' => 12,
            'hills' => 'hilly',
            'name' => 'Sunday hills',
        ])
        ->assertOk()
        ->assertSee('Saved “Sunday hills”');

    $savedRoute = SavedRoute::query()->sole();

    expect($savedRoute->surface)->toBe('dirt')
        ->and($savedRoute->elevation)->toBe('maximize')
        ->and($savedRoute->coordinates[0])->toBe([3.72, 51.05]);
});

it('says so when the map knows no such place', function (): void {
    TracewayServer::actingAs(User::factory()->create())
        ->tool(PlanLoopTool::class, ['start' => 'Atlantis', 'sport' => 'run', 'distance_km' => 10])
        ->assertHasErrors(['No place called "Atlantis" on the map.']);

    expect(SavedRoute::query()->count())->toBe(0);
});

it('says so when the routing engine finds no loop', function (): void {
    $this->app->instance(RoutingEngine::class, mcpEngine(fails: true));

    TracewayServer::actingAs(User::factory()->create())
        ->tool(PlanLoopTool::class, ['start' => 'Westerlo, Belgium', 'sport' => 'gravel', 'distance_km' => 60])
        ->assertHasErrors(['No loop came back from that start.']);

    expect(SavedRoute::query()->count())->toBe(0);
});

it('asks for what is missing in plain words', function (): void {
    TracewayServer::actingAs(User::factory()->create())
        ->tool(PlanLoopTool::class, [])
        ->assertHasErrors([
            'Say where the loop starts, like "Westerlo, Belgium" or a street address.',
            'Say which sport: road, gravel, mtb, run, trail_run, walk, or hike.',
            'Say how far, in kilometres.',
        ]);
});

it('refuses a sport it does not plan', function (): void {
    TracewayServer::actingAs(User::factory()->create())
        ->tool(PlanLoopTool::class, ['start' => 'Westerlo, Belgium', 'sport' => 'rowing', 'distance_km' => 10])
        ->assertHasErrors(['Pick one sport: road, gravel, mtb, run, trail_run, walk, or hike.']);
});

it('plans a sprint triathlon and leaves the swim to draw', function (): void {
    TracewayServer::actingAs(User::factory()->create())
        ->tool(PlanMultisportTool::class, [
            'start' => 'Westerlo, Belgium',
            'event' => 'triathlon',
            'race' => 'sprint',
        ])
        ->assertOk()
        ->assertSee('Saved “Sprint Triathlon from Westerlo”')
        ->assertSee('draw the swim in the planner');

    $savedRoute = SavedRoute::query()->sole();

    expect($savedRoute->layout)->toBe('loops')
        ->and($savedRoute->legs)->toHaveCount(3)
        ->and($savedRoute->legs[0]['discipline'] ?? null)->toBe('swim')
        ->and($savedRoute->legs[1]['discipline'] ?? null)->toBe('road')
        ->and($savedRoute->legs[1]['distance_metres'] ?? null)->toBe(20_400)
        ->and($savedRoute->legs[2]['discipline'] ?? null)->toBe('run')
        ->and($savedRoute->legs[2]['distance_metres'] ?? null)->toBe(5_400);
});

it('plans a brick from legs it is given', function (): void {
    TracewayServer::actingAs(User::factory()->create())
        ->tool(PlanMultisportTool::class, [
            'start' => '51.05, 3.72',
            'legs' => [
                ['sport' => 'gravel', 'distance_km' => 30],
                ['sport' => 'trail_run', 'distance_km' => 8],
            ],
        ])
        ->assertOk()
        ->assertSee('30.4 km gravel ride, then 8.4 km trail run');

    expect(SavedRoute::query()->sole()->legs)->toHaveCount(2);
});

it('lists only the routes of the person who signed in', function (): void {
    $user = User::factory()->create();
    SavedRoute::factory()->for($user)->create(['name' => 'Mine']);
    SavedRoute::factory()->create(['name' => 'Someone else']);

    TracewayServer::actingAs($user)
        ->tool(ListRoutesTool::class, [])
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Someone else');
});

it('asks an AI tool to sign in before it plans anything', function (): void {
    $keys = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($keys, $privateKey);
    config([
        'passport.private_key' => $privateKey,
        'passport.public_key' => openssl_pkey_get_details($keys)['key'],
    ]);

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

it('tells AI tools where to sign in', function (): void {
    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp'));

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('registration_endpoint', url('/oauth/register'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

/**
 * An AI tool the rider let in, with one live token and its refresh token.
 */
function connectApp(User $user, string $name = 'Claude'): string
{
    $clientId = (string) Str::uuid();

    DB::table('oauth_clients')->insert([
        'id' => $clientId,
        'name' => $name,
        'redirect_uris' => '["https://claude.ai/api/mcp/auth_callback"]',
        'grant_types' => '["authorization_code","refresh_token"]',
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('oauth_access_tokens')->insert([
        'id' => $tokenId = Str::random(80),
        'user_id' => $user->id,
        'client_id' => $clientId,
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addDays(30),
    ]);
    DB::table('oauth_refresh_tokens')->insert([
        'id' => Str::random(80),
        'access_token_id' => $tokenId,
        'revoked' => false,
        'expires_at' => now()->addDays(90),
    ]);

    return $clientId;
}

it('shows the AI tools a rider connected, and disconnects one', function (): void {
    $user = User::factory()->create();
    $clientId = connectApp($user);
    connectApp(User::factory()->create(), "Someone else's app");

    $this->actingAs($user)
        ->get(route('privacy.edit'))
        ->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
            ->has('apps', 1)
            ->where('apps.0.id', $clientId)
            ->where('apps.0.name', 'Claude'));

    $this->actingAs($user)
        ->delete(route('privacy.apps.disconnect', $clientId))
        ->assertRedirect();

    expect(DB::table('oauth_access_tokens')->where('client_id', $clientId)->value('revoked'))->toBeTruthy()
        ->and(DB::table('oauth_refresh_tokens')->where('revoked', operator: true)->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('privacy.edit'))
        ->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia->has('apps', 0));
});

it('shows a rider how to add the connector, and the apps they added', function (): void {
    $this->get(route('assistants.edit'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $clientId = connectApp($user);
    connectApp(User::factory()->create(), "Someone else's app");

    $this->actingAs($user)
        ->get(route('assistants.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
            ->component('settings/assistants')
            ->where('connector_url', url('/mcp'))
            ->has('apps', 1)
            ->where('apps.0.id', $clientId)
            ->where('apps.0.name', 'Claude'));
});

it('cannot disconnect an app for someone else', function (): void {
    $clientId = connectApp(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->delete(route('privacy.apps.disconnect', $clientId))
        ->assertRedirect();

    expect(DB::table('oauth_access_tokens')->where('client_id', $clientId)->value('revoked'))->toBeFalsy();
});

it('takes the AI tool access with a deleted account', function (): void {
    $user = User::factory()->create();
    connectApp($user);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(DB::table('oauth_access_tokens')->count())->toBe(0)
        ->and(DB::table('oauth_refresh_tokens')->count())->toBe(0);
});

it('draws the plan in the chat with the Traceway map', function (): void {
    TracewayServer::actingAs(User::factory()->create())
        ->resource(RouteMapApp::class)
        ->assertSee(['createMcpApp', 'route_drawing', 'Open the map', 'traceway:map-ready', route('mcp.apps.route-map')]);

    expect(new PlanLoopTool()->toArray()['_meta']['ui'] ?? null)->toBe(['resourceUri' => 'ui://traceway/route-map', 'visibility' => ['model', 'app']])
        ->and(new PlanMultisportTool()->toArray()['_meta']['ui']['resourceUri'] ?? null)->toBe('ui://traceway/route-map')
        ->and(new RouteDrawingTool()->toArray()['_meta']['ui']['visibility'] ?? null)->toBe(['app']);
});

it('sends the line for the map with the plan, outside what the model reads', function (): void {
    $user = User::factory()->create();
    $result = fn (TestResponse $testResponse): array => (fn (): array => $this->response->toArray()['result'])->call($testResponse);

    $loop = $result(TracewayServer::actingAs($user)
        ->tool(PlanLoopTool::class, ['start' => 'Westerlo, Belgium', 'sport' => 'road', 'distance_km' => 40]));
    $brick = $result(TracewayServer::actingAs($user)
        ->tool(PlanMultisportTool::class, ['start' => 'Westerlo, Belgium', 'event' => 'brick', 'race' => 'sprint']));

    expect($loop['structuredContent']['drawing']['line'][0] ?? null)->toBe([4.92, 51.09])
        ->and($loop['structuredContent']['name'] ?? null)->toBe('40.4 km road ride from Westerlo')
        ->and($loop['content'][0]['text'] ?? '')->toContain('40.4 km road ride from Westerlo')
        ->and($loop['content'][0]['text'] ?? '')->not->toContain('drawing')
        ->and($loop['structuredContent']['embed_url'] ?? '')->toContain('/embed?signature=')
        ->and($loop['content'][0]['text'] ?? '')->not->toContain('/embed')
        ->and($brick['structuredContent']['drawing']['legs'] ?? [])->toHaveCount(2)
        ->and($brick['content'][0]['text'] ?? '')->not->toContain('drawing');
});

it('ships a card script that parses, since a broken one leaves the chat waiting', function (): void {
    $html = view('mcp.route-map-app', [
        'showStatus' => true,
        'traceway' => 'https://traceway.test',
        'mapScript' => 'https://traceway.test/mcp/apps/route-map.js',
        'mapCode' => '',
    ])->render();
    preg_match_all('#<script type="module">(.*?)</script>#s', $html, $scripts);

    expect($scripts[1])->not->toBeEmpty();

    foreach ([...$scripts[1], (string) file_get_contents(resource_path('mcp/route-map.js'))] as $script) {
        $file = sys_get_temp_dir().'/traceway-card-'.Str::random(8).'.mjs';
        file_put_contents($file, $script);
        $check = Process::run(['node', '--check', $file]);
        unlink($file);

        expect($check->successful())->toBeTrue($check->errorOutput());
    }
});

it('lets the card in the chat fetch its map, fresh each time', function (): void {
    $csp = new RouteMapApp()->resolvedAppMeta()['csp'] ?? [];

    expect($csp['frameDomains'] ?? [])->toBe([rtrim(url('/'), '/')])
        ->and($csp['connectDomains'] ?? [])->toBe([rtrim(url('/'), '/'), 'https://unpkg.com', 'https://tiles.openfreemap.org', 'https://fonts.bunny.net']);

    $script = $this->get(route('mcp.apps.route-map'))->assertOk();

    expect($script->headers->get('Content-Type'))->toBe('text/javascript; charset=utf-8')
        ->and($script->headers->get('Access-Control-Allow-Origin'))->toBe('*')
        ->and($script->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($script->getFile()->getContent())->toContain('export async function mount');

    $this->get(route('mcp.apps.route-map'), ['If-None-Match' => (string) $script->headers->get('ETag')])
        ->assertStatus(304);
});

it('gives the map the line of a route the rider saved', function (): void {
    $user = User::factory()->create();
    $savedRoute = SavedRoute::factory()->for($user)->create(['name' => 'Morning loop']);

    TracewayServer::actingAs($user)
        ->tool(RouteDrawingTool::class, ['id' => $savedRoute->id])
        ->assertOk()
        ->assertSee(['Morning loop', '/share/']);
});

it("keeps the line of someone else's route from the map", function (): void {
    $savedRoute = SavedRoute::factory()->create();

    TracewayServer::actingAs(User::factory()->create())
        ->tool(RouteDrawingTool::class, ['id' => $savedRoute->id])
        ->assertHasErrors(['That route is not in your library.']);
});
