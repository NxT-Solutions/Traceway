<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Account\RiderId;
use App\Commands\SaveRouteCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\LineSimplifier;
use App\Domain\Routing\Place;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Mcp\Apps\RouteMapApp;
use App\Mcp\RouteLinks;
use App\Mcp\SportDefaults;
use App\Mcp\StartPoint;
use App\Queries\Routes\RouteDrawingQuery;
use App\Routes\CoordinateLine;
use App\Routes\RouteName;
use App\Routes\SavedRouteLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[Name(value: 'plan_loop')]
#[Title(value: 'Plan a loop')]
#[Description(value: "Plans a loop route that starts and ends at one place, for a run, trail run, walk, hike, road ride, gravel ride, or mountain-bike ride. The route comes from OpenStreetMap roads and paths. It is saved to the person's Traceway library, and the answer has a link to the map and files for a Garmin, COROS, Suunto, Wahoo, or Hammerhead. Never invent coordinates for the route itself.")]
#[IsOpenWorld]
#[RendersApp(resource: RouteMapApp::class)]
final class PlanLoopTool extends Tool
{
    use AnswersWithDrawing;
    use ReadsArguments;

    public function handle(
        Request $request,
        RoutingEngine $routingEngine,
        StartPoint $startPoint,
        DispatchesCommands $dispatchesCommands,
        RouteDrawingQuery $routeDrawingQuery,
    ): Response|ResponseFactory {
        $riderId = $this->riderId($request);

        if ($riderId === null) {
            return Response::error('Sign in to Traceway first.');
        }

        $validated = $request->validate([
            'start' => ['required', 'string', 'max:200'],
            'sport' => ['required', 'string', 'in:'.implode(',', array_column(Sport::cases(), 'value'))],
            'distance_km' => ['required', 'numeric', 'min:1', 'max:300'],
            'surface' => ['nullable', 'string', 'in:paved,mixed,dirt'],
            'hills' => ['nullable', 'string', 'in:flat,any,hilly'],
            'quiet_roads' => ['nullable', 'boolean'],
            'via' => ['nullable', 'string', 'max:200'],
            'name' => ['nullable', 'string', 'max:80'],
        ], [
            'start.required' => 'Say where the loop starts, like "Westerlo, Belgium" or a street address.',
            'sport.required' => 'Say which sport: road, gravel, mtb, run, trail_run, walk, or hike.',
            'sport.in' => 'Pick one sport: road, gravel, mtb, run, trail_run, walk, or hike.',
            'distance_km.required' => 'Say how far, in kilometres.',
            'distance_km.numeric' => 'Say how far, in kilometres.',
            'distance_km.min' => 'A loop is at least 1 km.',
            'distance_km.max' => 'A loop is at most 300 km.',
        ]);

        $sport = Sport::from($this->text($validated, 'sport'));

        try {
            $start = $startPoint->resolve($this->text($validated, 'start'));
            $via = $this->text($validated, 'via') === '' ? null : $startPoint->resolve($this->text($validated, 'via'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            return Response::error($invalidArgumentException->getMessage());
        } catch (Throwable) {
            return Response::error('The place search did not answer. Try again in a moment, or give "latitude, longitude".');
        }

        $surface = Surface::tryFrom($this->text($validated, 'surface')) ?? $sport->defaultSurface();
        $elevation = match ($this->text($validated, 'hills')) {
            'flat' => ElevationBias::Minimize,
            'hilly' => ElevationBias::Maximize,
            default => ElevationBias::Neutral,
        };
        $quiet = ($validated['quiet_roads'] ?? true) !== false;
        $distanceKm = $this->number($validated, 'distance_km');

        try {
            $routes = $routingEngine->candidates(new RouteQuery(
                $sport,
                Shape::Loop,
                (int) round($distanceKm * 1000),
                $surface,
                $elevation,
                $start->latitude,
                $start->longitude,
                $via?->latitude,
                $via?->longitude,
                $quiet,
            ));
        } catch (Throwable) {
            $routes = [];
        }

        $route = $routes[0] ?? null;

        if ($route === null) {
            return Response::error('No loop came back from that start. Try a different distance, or a start closer to roads or paths.');
        }

        $name = $this->text($validated, 'name');
        $name = $name !== '' ? $name : Str::limit(sprintf('%s km %s from %s', RouteLinks::kilometres($route->distanceMetres), SportDefaults::label($sport), Str::before($start->name, ',')), 80, '');

        $saved = $dispatchesCommands->dispatch(new SaveRouteCommand(
            new RiderId($riderId),
            new RouteName($name),
            $sport,
            $surface,
            $elevation,
            $quiet ? 'quiet' : 'any',
            $route->distanceMetres,
            $route->ascentMetres,
            CoordinateLine::ride(LineSimplifier::simplify($route->coordinates, LineSimplifier::ROUTE_METRES)),
            userCoordinates: null,
            finishCoordinates: null,
        ));

        if (! $saved instanceof SavedRouteLink) {
            return Response::error('The loop was planned but could not be saved. Try again.');
        }

        $links = RouteLinks::for($saved->id);
        $kilometres = RouteLinks::kilometres($route->distanceMetres);

        return $this->withDrawing([
            'id' => $saved->id,
            'name' => $name,
            'sport' => $sport->value,
            'distance_km' => $kilometres,
            'surface' => $surface->value,
            'start' => ['name' => $start->name, 'latitude' => $start->latitude, 'longitude' => $start->longitude],
            'via' => $via instanceof Place ? ['name' => $via->name, 'latitude' => $via->latitude, 'longitude' => $via->longitude] : null,
            ...$links,
            'summary' => sprintf(
                'Saved “%s”: a %s km %s. See it on the map: %s. For a Garmin, download %s. For another watch or bike computer, download %s.',
                $name,
                $kilometres,
                SportDefaults::label($sport),
                $links['map_url'],
                $links['garmin_url'],
                $links['gpx_url'],
            ),
        ], $routeDrawingQuery, $saved->id, $riderId);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'start' => $schema->string()
                ->description('Where the loop starts and ends: a town, a street address, a trailhead, or "latitude, longitude". Add the country when a name could be in several places.')
                ->required(),
            'sport' => $schema->string()
                ->enum(array_column(Sport::cases(), 'value'))
                ->description('road, gravel, mtb (mountain bike), run, trail_run, walk, or hike.')
                ->required(),
            'distance_km' => $schema->number()
                ->min(1)
                ->max(300)
                ->description('How long the loop should be, in kilometres. The loop comes close to it, not exactly.')
                ->required(),
            'surface' => $schema->string()
                ->enum(['paved', 'mixed', 'dirt'])
                ->description('The ground underfoot or under the tyres. Leave it out to use what suits the sport.'),
            'hills' => $schema->string()
                ->enum(['flat', 'any', 'hilly'])
                ->description('Avoid climbing, do not mind, or look for it. Defaults to any.'),
            'quiet_roads' => $schema->boolean()
                ->description('Put paths and quiet streets first. Defaults to true.'),
            'via' => $schema->string()
                ->description('A place the loop should pass through, like a village or a café. Optional.'),
            'name' => $schema->string()
                ->max(80)
                ->description('A name for the saved route. Optional.'),
        ];
    }
}
