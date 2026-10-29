<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Account\RiderId;
use App\Commands\SaveRouteCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\LandLeg;
use App\Domain\Routing\Layout;
use App\Domain\Routing\LineSimplifier;
use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\MultisportPlanner;
use App\Domain\Routing\MultisportQuery;
use App\Domain\Routing\MultisportRoute;
use App\Domain\Routing\PlannedLeg;
use App\Domain\Routing\Sport;
use App\Domain\Routing\SwimLeg;
use App\Mcp\Apps\RouteMapApp;
use App\Mcp\RaceDistances;
use App\Mcp\RouteLinks;
use App\Mcp\SportDefaults;
use App\Mcp\StartPoint;
use App\Queries\Routes\RouteDrawingQuery;
use App\Queries\Routes\SignedRouteLink;
use App\Routes\CoordinateLine;
use App\Routes\RiderAvoids;
use App\Routes\RouteName;
use App\Routes\SavedLeg;
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

#[Name(value: 'plan_multisport')]
#[Title(value: 'Plan a brick, triathlon, duathlon, or aquabike')]
#[Description(value: "Plans a multisport session from one transition: each land leg is its own loop that starts and ends there. Pick an event and a race distance, or give the legs yourself. A swim cannot be routed on water, so the person draws it in the Traceway planner. The plan is saved to the person's library with a file for each leg.")]
#[IsOpenWorld]
#[RendersApp(resource: RouteMapApp::class)]
final class PlanMultisportTool extends Tool
{
    use AnswersWithDrawing;
    use ReadsArguments;

    private const array LAND_SPORTS = ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike'];

    public function handle(
        Request $request,
        MultisportPlanner $multisportPlanner,
        StartPoint $startPoint,
        DispatchesCommands $dispatchesCommands,
        RouteDrawingQuery $routeDrawingQuery,
        RiderAvoids $riderAvoids,
    ): Response|ResponseFactory {
        $riderId = $this->riderId($request);

        if ($riderId === null) {
            return Response::error('Sign in to Traceway first.');
        }

        $validated = $request->validate([
            'start' => ['required', 'string', 'max:200'],
            'event' => ['nullable', 'string', 'in:'.implode(',', RaceDistances::events())],
            'race' => ['nullable', 'required_with:event', 'string', 'in:'.implode(',', RaceDistances::races())],
            'legs' => ['nullable', 'required_without:event', 'array', 'min:1', 'max:4'],
            'legs.*.sport' => ['required', 'string', 'in:'.implode(',', self::LAND_SPORTS)],
            'legs.*.distance_km' => ['required', 'numeric', 'min:1', 'max:300'],
            'quiet_roads' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:80'],
        ], [
            'start.required' => 'Say where the transition is, like "Lake Kinrooi, Belgium".',
            'legs.required_without' => 'Pick an event and a race distance, or give the legs.',
            'race.required_with' => 'Pick a race distance for the event, like sprint or olympic.',
        ]);

        $event = $this->text($validated, 'event');
        $race = $event === '' ? null : RaceDistances::find($event, $this->text($validated, 'race'));

        if ($event !== '' && $race === null) {
            return Response::error(sprintf('A %s has no race called %s.', $event, $this->text($validated, 'race')));
        }

        $quiet = ($validated['quiet_roads'] ?? true) !== false;
        $plannedLegs = $race === null ? $this->givenLegs($validated) : $race['legs'];
        $swimKm = $race['swim'] ?? null;

        try {
            $transition = $startPoint->resolve($this->text($validated, 'start'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            return Response::error($invalidArgumentException->getMessage());
        } catch (Throwable) {
            return Response::error('The place search did not answer. Try again in a moment, or give "latitude, longitude".');
        }

        $pin = [$transition->longitude, $transition->latitude];

        try {
            $plan = $multisportPlanner->plan($riderAvoids->applyToMultisport(new MultisportQuery(
                Layout::Loops,
                $transition->latitude,
                $transition->longitude,
                array_map(fn (array $leg): LandLeg => new LandLeg(
                    $leg[0],
                    (int) round($leg[1] * 1000),
                    $leg[0]->defaultSurface(),
                    ElevationBias::Neutral,
                    $quiet,
                ), $plannedLegs),
                $swimKm === null ? null : new SwimLeg([$pin]),
            ), $riderId));
        } catch (MapServerBusy) {
            return Response::error('The map server is busy. Try again in a minute.');
        } catch (Throwable) {
            return Response::error('No plan came back from that transition. Try shorter legs, or a transition closer to roads.');
        }

        $landLegs = array_values(array_filter($plan->legs, fn (PlannedLeg $plannedLeg): bool => $plannedLeg->sport instanceof Sport));
        $first = $landLegs[0] ?? null;

        if (! $first instanceof PlannedLeg || ! $first->sport instanceof Sport) {
            return Response::error('No plan came back from that transition. Try again.');
        }

        $name = $this->text($validated, 'name');
        $name = $name !== '' ? $name : Str::limit(sprintf('%s from %s', $event !== '' ? Str::headline($this->text($validated, 'race').' '.$event) : 'Multisport', Str::before($transition->name, ',')), 80, '');

        $saved = $dispatchesCommands->dispatch(new SaveRouteCommand(
            new RiderId($riderId),
            new RouteName($name),
            $first->sport,
            $first->sport->defaultSurface(),
            ElevationBias::Neutral,
            $quiet ? 'quiet' : 'any',
            $plan->distanceMetres(),
            $plan->ascentMetres(),
            CoordinateLine::ride($this->line($landLegs)),
            userCoordinates: null,
            finishCoordinates: null,
            layout: Layout::Loops,
            legs: array_map(fn (PlannedLeg $plannedLeg): SavedLeg => new SavedLeg(
                $plannedLeg->sport,
                $plannedLeg->distanceMetres,
                $plannedLeg->ascentMetres,
                $plannedLeg->sport instanceof Sport
                    ? LineSimplifier::simplify($plannedLeg->coordinates, LineSimplifier::ROUTE_METRES)
                    : $plannedLeg->coordinates,
            ), $plan->legs),
        ));

        if (! $saved instanceof SavedRouteLink) {
            return Response::error('The plan was made but could not be saved. Try again.');
        }

        return $this->withDrawing($this->answer($saved->id, $name, $plan, $swimKm), $routeDrawingQuery, $saved->id, $riderId);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'start' => $schema->string()
                ->description('The transition, where every leg starts and ends: a town, an address, a lake, or "latitude, longitude".')
                ->required(),
            'event' => $schema->string()
                ->enum(RaceDistances::events())
                ->description('brick (ride then run), triathlon (swim, ride, run), duathlon (run, ride, run), or aquabike (swim, ride). Leave it out to give the legs yourself.'),
            'race' => $schema->string()
                ->enum(RaceDistances::races())
                ->description('The race distance for the event. Triathlon: super_sprint, eighth, sprint, quarter, olympic, t100, ironman_70_3, ironman. Duathlon: sprint, standard, powerman. Brick: sprint, olympic, t100, ironman_70_3, ironman. Aquabike: standard, middle, long.'),
            'legs' => $schema->array()
                ->items($schema->object([
                    'sport' => $schema->string()->enum(self::LAND_SPORTS)->required(),
                    'distance_km' => $schema->number()->min(1)->max(300)->required(),
                ]))
                ->max(4)
                ->description('Land legs in order, when there is no event. Up to four.'),
            'quiet_roads' => $schema->boolean()
                ->description('On foot, put walkways first. On a bike, put cycle paths and quiet lanes first, and busy roads lose. Defaults to true.'),
            'name' => $schema->string()
                ->max(80)
                ->description('A name for the saved plan. Optional.'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     * @return list<array{0: Sport, 1: float}>
     */
    private function givenLegs(array $validated): array
    {
        $legs = is_array($validated['legs'] ?? null) ? $validated['legs'] : [];

        return array_values(array_map(
            fn (mixed $leg): array => [
                Sport::from(is_array($leg) ? $this->text($leg, 'sport') : ''),
                is_array($leg) ? $this->number($leg, 'distance_km') : 0.0,
            ],
            $legs,
        ));
    }

    /**
     * Every land leg, one after the other, as the line the library draws.
     *
     * @param  list<PlannedLeg>  $landLegs
     * @return list<array{0: float, 1: float}>
     */
    private function line(array $landLegs): array
    {
        $line = [];

        foreach ($landLegs as $landLeg) {
            array_push($line, ...$landLeg->coordinates);
        }

        return LineSimplifier::simplify($line, LineSimplifier::ROUTE_METRES);
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(string $id, string $name, MultisportRoute $multisportRoute, ?float $swimKm): array
    {
        $links = RouteLinks::for($id);
        $legs = [];

        foreach ($multisportRoute->legs as $index => $plannedLeg) {
            $legs[] = [
                'discipline' => $plannedLeg->sport instanceof Sport ? $plannedLeg->sport->value : 'swim',
                'distance_km' => $plannedLeg->sport instanceof Sport ? RouteLinks::kilometres($plannedLeg->distanceMetres) : $swimKm,
                'garmin_url' => $plannedLeg->sport instanceof Sport ? SignedRouteLink::fitLeg($id, $index + 1) : null,
            ];
        }

        $summary = sprintf(
            'Saved “%s”: %s, %s km on land. See it on the map: %s. Each land leg has its own file for the watch.',
            $name,
            implode(', then ', array_map(
                fn (array $leg): string => $leg['discipline'] === 'swim'
                    ? sprintf('a %s km swim', $leg['distance_km'])
                    : sprintf('%s km %s', $leg['distance_km'], SportDefaults::label(Sport::from($leg['discipline']))),
                $legs,
            )),
            RouteLinks::kilometres($multisportRoute->distanceMetres()),
            $links['map_url'],
        );

        if ($swimKm !== null) {
            $summary .= ' Water has no roads, so draw the swim in the planner before you go.';
        }

        return [
            'id' => $id,
            'name' => $name,
            'distance_km' => RouteLinks::kilometres($multisportRoute->distanceMetres()),
            'legs' => $legs,
            ...$links,
            'summary' => $summary,
        ];
    }
}
