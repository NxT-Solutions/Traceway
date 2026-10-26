import { delay, type HttpHandler, HttpResponse, http, passthrough } from 'msw';
// Types `context.msw`, so a story can `beforeEach({ msw }) { msw.use(…) }`.
import type {} from 'msw-storybook-addon/csf3';
import { type AvoidedRoad, metresToLine } from '@/lib/avoided-roads';
import { legDeviceName } from '@/lib/multisport';
import { cutsOf } from '@/lib/reshape';
import { routeGpx } from '@/lib/route-details';
import type { RouteServiceStatus } from '@/types/route-services';
import { sharedProps } from './inertia-page';
import {
    type Coordinate,
    detailsAlong,
    libraryLegLines,
    libraryRoutes,
    loopFrom,
    metres,
    places,
    previewOf,
    routeServices,
    type SavedRoute,
    stopsAlong,
    westerlo,
} from './planner-pages';
import { inertiaPage } from './server';

/** One request the planner sent to the mock server, for a play function to check. */
export type PlannerRequest = {
    method: string;
    /** The path and query, such as `/routes/saved/…/details?leg=2`. */
    path: string;
    /** The JSON body, or null for a file, a form, or no body. */
    body: Record<string, unknown> | null;
};

export type PlannerServer = {
    /** Every handler, for `msw.use(...server.handlers)`. */
    handlers: HttpHandler[];
    /** What the planner asked for, oldest first. */
    requests: PlannerRequest[];
    /** The requests to one path, such as `/routes/preview`. */
    sent: (method: string, path: string | RegExp) => PlannerRequest[];
    /** The rider's library as the server holds it now, after any save or delete. */
    library: () => SavedRoute[];
    /** The roads the rider avoids as the server holds them now. */
    avoided: () => AvoidedRoad[];
    /** The loops the last plan drew, the first one chosen. */
    drawn: () => Coordinate[][];
};

type Options = {
    /** The routes the rider saved before, newest first. */
    library?: SavedRoute[];
    /** The services the rider connected, as HandleInertiaRequests shares them. */
    services?: RouteServiceStatus[];
    /** How long each answer takes, in milliseconds, so a story shows its busy states. */
    latency?: number;
    /** The roads the rider avoided before. */
    avoided?: AvoidedRoad[];
};

const sendMessages: Record<string, string> = {
    wahoo: 'In your Wahoo app. It syncs to your ELEMNT or ACE the next time it connects.',
    ride_with_gps:
        'In your Ride with GPS library. Choose Send to Device there to put it on your bike computer.',
};

/** A FIT file header with no records: a small download in the course file's format, not a course a device can follow. */
function fitBytes(): Uint8Array {
    return new Uint8Array([
        14, 0x20, 0x84, 0x08, 0, 0, 0, 0, 0x2e, 0x46, 0x49, 0x54, 0, 0, 0, 0,
    ]);
}

/** The file name CourseDownload gives a course: its name in lower case, words joined by dashes. */
function slug(name: string): string {
    const words = name
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

    return words === '' ? 'traceway' : words;
}

function course(
    format: 'fit' | 'gpx',
    name: string,
    line: Coordinate[],
): Response {
    const headers = {
        'Content-Type':
            format === 'fit'
                ? 'application/octet-stream'
                : 'application/gpx+xml',
        'Content-Disposition': `attachment; filename="${slug(name)}.${format}"`,
    };

    return format === 'fit'
        ? new HttpResponse(fitBytes(), { headers })
        : new HttpResponse(routeGpx(name, line, detailsAlong(line)), {
              headers,
          });
}

/** The page's own address, so an Inertia answer keeps the story's frame where it is. */
function storyAddress(): string {
    return `${window.location.pathname}${window.location.search}`;
}

function coordinatesOf(value: unknown): Coordinate[] {
    return Array.isArray(value)
        ? value.filter(
              (point): point is Coordinate =>
                  Array.isArray(point) &&
                  point.length === 2 &&
                  typeof point[0] === 'number' &&
                  typeof point[1] === 'number',
          )
        : [];
}

const number = (value: unknown, fallback: number) =>
    typeof value === 'number' && Number.isFinite(value) ? value : fallback;

const text = (value: unknown, fallback: string) =>
    typeof value === 'string' && value.trim() !== '' ? value : fallback;

const invalid = (message: string, field: string) =>
    HttpResponse.json(
        { message, errors: { [field]: [message] } },
        { status: 422 },
    );

/**
 * The planner's whole server side, from fixtures, for Mock Service Worker: place search, snapping, loops, details,
 * stops, saving, the library, sending to a device, and course files. Each answer has the shape its controller
 * returns. The lines are the Westerlo sketch scaled to the asked length (see `loopFrom`), never a plan from the
 * routing engine. Each call starts a fresh library, so a story that deletes a route does not change the next one.
 */
export function plannerServer(options: Options = {}): PlannerServer {
    const latency = options.latency ?? 250;
    const services = options.services ?? routeServices;
    let library = [...(options.library ?? libraryRoutes)];
    let avoided = [...(options.avoided ?? [])];
    // Every road the server kept, removed or not.
    let known = [...avoided];
    let avoidedCount = 0;
    // The loops a plan drew last, so an avoided road can be cut from the one a tap landed on.
    let drawn: Coordinate[][] = [];
    const usesRoad = (line: Coordinate[], road: AvoidedRoad) =>
        metresToLine([road.longitude, road.latitude], line) < 25;
    const legLines: Record<string, Coordinate[][]> = { ...libraryLegLines };
    const requests: PlannerRequest[] = [];

    async function note(request: Request): Promise<void> {
        const url = new URL(request.url);
        const body: unknown = await request
            .clone()
            .json()
            .catch(() => null);

        requests.push({
            method: request.method,
            path: `${url.pathname}${url.search}`,
            body:
                typeof body === 'object' &&
                body !== null &&
                !Array.isArray(body)
                    ? (body as Record<string, unknown>)
                    : null,
        });
    }

    async function read(request: Request): Promise<Record<string, unknown>> {
        await note(request);
        await delay(latency);
        const body: unknown = await request.json().catch(() => ({}));

        return typeof body === 'object' && body !== null
            ? (body as Record<string, unknown>)
            : {};
    }

    const find = (id: unknown) =>
        library.find((route) => route.id === String(id)) ?? null;

    const handlers: HttpHandler[] = [
        // SearchPlacesController: up to five places, the ones near the map first.
        http.post('/routes/places', async ({ request }) => {
            const body = await read(request);
            const query = text(body.query, '').trim().toLowerCase();

            if (query.length < 2) {
                return invalid(
                    'The query field must be at least 2 characters.',
                    'query',
                );
            }

            const near = {
                longitude: number(body.longitude, westerlo.longitude),
                latitude: number(body.latitude, westerlo.latitude),
            };
            const away = (place: Coordinate) =>
                metres([[near.longitude, near.latitude], place]);

            return HttpResponse.json({
                places: places
                    .filter((place) =>
                        `${place.name} ${place.area}`
                            .toLowerCase()
                            .includes(query),
                    )
                    .sort(
                        (a, b) =>
                            away([a.longitude, a.latitude]) -
                            away([b.longitude, b.latitude]),
                    )
                    .slice(0, 5),
            });
        }),

        // NamePlaceController: the village or town around a point, or null.
        http.post('/routes/place', async ({ request }) => {
            const body = await read(request);
            const point: Coordinate = [
                number(body.longitude, westerlo.longitude),
                number(body.latitude, westerlo.latitude),
            ];
            const nearest = places
                .filter((place) => place.kind === 'town')
                .map((place) => ({
                    name: place.name,
                    away: metres([point, [place.longitude, place.latitude]]),
                }))
                .sort((a, b) => a.away - b.away)[0];

            return HttpResponse.json({
                name:
                    nearest !== undefined && nearest.away < 4000
                        ? nearest.name
                        : null,
            });
        }),

        // ReshapeRouteController: the legs the change touches drawn again as straight lines, the rest kept.
        http.post('/routes/reshape', async ({ request }) => {
            const body = await read(request);
            const line = coordinatesOf(body.line);
            let waypoints = coordinatesOf(body.waypoints);

            if (line.length < 2 || waypoints.length < 2) {
                return invalid(
                    'The line field must have at least 2 items.',
                    'line',
                );
            }

            const cuts = cutsOf(line, waypoints);
            let legs: (Coordinate[] | null)[] = cuts
                .slice(1)
                .map((cut, index) => line.slice(cuts[index], cut + 1));
            const change =
                typeof body.change === 'object' && body.change !== null
                    ? (body.change as Record<string, unknown>)
                    : {};
            const index = number(change.index, 0);
            const point: Coordinate = [
                number(change.longitude, 0),
                number(change.latitude, 0),
            ];

            if (change.kind === 'move') {
                waypoints = waypoints.map((stop, at) =>
                    at === index ? point : stop,
                );
                legs = legs.map((leg, at) =>
                    at === index - 1 || at === index ? null : leg,
                );
            } else if (change.kind === 'insert') {
                waypoints = [
                    ...waypoints.slice(0, index),
                    point,
                    ...waypoints.slice(index),
                ];
                legs =
                    index >= legs.length + 1
                        ? [...legs, null]
                        : [
                              ...legs.slice(0, index - 1),
                              null,
                              null,
                              ...legs.slice(index),
                          ];
            } else if (change.kind === 'remove') {
                waypoints = waypoints.filter((_, at) => at !== index);
                legs =
                    index === legs.length
                        ? legs.slice(0, -1)
                        : [
                              ...legs.slice(0, index - 1),
                              null,
                              ...legs.slice(index + 1),
                          ];
            } else {
                const asked = Array.isArray(change.legs) ? change.legs : [];
                legs = legs.map((leg, at) => (asked.includes(at) ? null : leg));
            }

            const changed = legs.flatMap((leg, at) =>
                leg === null ? [at] : [],
            );
            const joined: Coordinate[] = [];

            legs.forEach((leg, at) => {
                const piece = leg ?? [
                    waypoints[at] ?? point,
                    waypoints[at + 1] ?? point,
                ];

                joined.push(...(joined.length === 0 ? piece : piece.slice(1)));
            });

            if (body.mode === 'loop') {
                return HttpResponse.json({
                    ...previewOf(joined),
                    ascent_metres: 0,
                    waypoints,
                    changed_legs: changed,
                    reverse_share: 0,
                    crosses_avoided: [],
                });
            }

            const finish = body.finish === true;
            const userEnd = finish
                ? (cutsOf(joined, waypoints).at(-2) ?? joined.length - 1)
                : joined.length - 1;
            const user = joined.slice(0, userEnd + 1);
            const finishLine = finish ? joined.slice(userEnd) : [];

            return HttpResponse.json({
                distance_metres: metres(joined),
                ascent_metres: 0,
                coordinates: joined,
                user_coordinates: user,
                finish_coordinates: finishLine,
                user_metres: metres(user),
                finish_metres: metres(finishLine),
                legs_metres: cutsOf(
                    user,
                    finish ? waypoints.slice(0, -1) : waypoints,
                )
                    .slice(1)
                    .map((cut, at, all) =>
                        metres(user.slice(at === 0 ? 0 : all[at - 1], cut + 1)),
                    ),
                crosses_avoided: [],
            });
        }),

        // AvoidStretchController (AvoidedStretch): the way under the tap, kept; a miss far from the Kempen.
        http.post('/routes/avoided', async ({ request }) => {
            const body = await read(request);
            const point: Coordinate = [
                number(body.longitude, westerlo.longitude),
                number(body.latitude, westerlo.latitude),
            ];

            if (
                metres([point, [westerlo.longitude, westerlo.latitude]]) >
                40_000
            ) {
                return HttpResponse.json(
                    {
                        message:
                            'No road or path is close enough there. Tap right on the road.',
                    },
                    { status: 422 },
                );
            }

            const same = avoided.find(
                (road) => metres([point, [road.longitude, road.latitude]]) < 3,
            );

            if (same !== undefined) {
                return HttpResponse.json(same, { status: 201 });
            }

            // A road let back in and avoided again, as Undo does: the same way, kept anew.
            const before = known.find(
                (road) => metres([point, [road.longitude, road.latitude]]) < 3,
            );
            avoidedCount += 1;
            const id = `0199f2a4-6c1b-7d3e-9a8f-${String(avoidedCount).padStart(12, '0')}`;

            if (before !== undefined) {
                const again = { ...before, id };
                avoided = [...avoided, again];
                known = [...known, again];

                return HttpResponse.json(again, { status: 201 });
            }

            // The stretch of a drawn loop around the tap, or a short way across it.
            const line = drawn.find((loop) => metresToLine(point, loop) < 30);
            const nearest = line
                ?.map((vertex, index) => ({
                    index,
                    away: metres([point, vertex]),
                }))
                .sort((a, b) => a.away - b.away)[0];
            const shape: Coordinate[] =
                line !== undefined && nearest !== undefined
                    ? line.slice(
                          Math.max(0, nearest.index - 1),
                          nearest.index + 2,
                      )
                    : [
                          [point[0] - 0.0015, point[1]],
                          point,
                          [point[0] + 0.0015, point[1]],
                      ];
            const road: AvoidedRoad = {
                id,
                name: 'Netepad',
                latitude: point[1],
                longitude: point[0],
                shape,
                sport: text(body.sport, 'road'),
                surface: text(body.surface, 'paved'),
            };
            avoided = [...avoided, road];
            known = [...known, road];

            return HttpResponse.json(road, { status: 201 });
        }),

        // ForgetAvoidedStretchController: no content, or a 404 for a road already gone.
        http.delete('/routes/avoided/:id', async ({ request, params }) => {
            await note(request);
            await delay(latency);
            const id = String(params.id);

            if (!avoided.some((road) => road.id === id)) {
                return HttpResponse.json(
                    { message: 'Not Found' },
                    { status: 404 },
                );
            }

            avoided = avoided.filter((road) => road.id !== id);

            return new HttpResponse(null, { status: 204 });
        }),

        // SnapPointController: the point on the nearest way, or a miss far from the Kempen.
        http.post('/routes/snap', async ({ request }) => {
            const body = await read(request);
            const point: Coordinate = [
                number(body.longitude, westerlo.longitude),
                number(body.latitude, westerlo.latitude),
            ];

            if (
                metres([point, [westerlo.longitude, westerlo.latitude]]) >
                40_000
            ) {
                return HttpResponse.json(
                    {
                        message:
                            'No road or path is close enough to put a point there.',
                    },
                    { status: 422 },
                );
            }

            return HttpResponse.json({
                latitude: Number(point[1].toFixed(5)),
                longitude: Number(point[0].toFixed(5)),
            });
        }),

        // PreviewRouteController (RoutePreview): three loops from the pin, the first one on top.
        http.post('/routes/preview', async ({ request }) => {
            const body = await read(request);
            const distance = number(body.distance_metres, 0);

            if (distance < 5000 || distance > 200_000) {
                return invalid(
                    'The distance metres field must be between 5000 and 200000.',
                    'distance_metres',
                );
            }

            const start = {
                longitude: number(body.longitude, westerlo.longitude),
                latitude: number(body.latitude, westerlo.latitude),
            };
            // The loops that keep off the avoided roads first, as Valhalla leaves them out.
            const lines = [0, 1, 2]
                .map((index) => loopFrom(start, distance, index))
                .sort(
                    (a, b) =>
                        avoided.filter((road) => usesRoad(a, road)).length -
                        avoided.filter((road) => usesRoad(b, road)).length,
                );
            drawn = lines;
            const routes = lines.map((line) => ({
                ...previewOf(line),
                waypoints: stopsOf(line),
                crosses_avoided: avoided
                    .filter((road) => usesRoad(line, road))
                    .map((road) => road.id),
            }));
            const [first] = routes;

            return HttpResponse.json({ ...first, routes });
        }),

        // BuildRouteController (BuiltRoute), as with Follow roads off: the line through the points, closed when asked.
        http.post('/routes/build', async ({ request }) => {
            const body = await read(request);
            const start: Coordinate = [
                number(body.longitude, westerlo.longitude),
                number(body.latitude, westerlo.latitude),
            ];
            const points = Array.isArray(body.points)
                ? body.points.flatMap((point: unknown): Coordinate[] => {
                      const record =
                          typeof point === 'object' && point !== null
                              ? (point as Record<string, unknown>)
                              : {};

                      return typeof record.longitude === 'number' &&
                          typeof record.latitude === 'number'
                          ? [[record.longitude, record.latitude]]
                          : [];
                  })
                : [];
            const waypoints = [start, ...points];

            if (waypoints.length < 2) {
                return HttpResponse.json(
                    { message: 'Add a point on the map, then draw the route.' },
                    { status: 422 },
                );
            }

            const last = waypoints[waypoints.length - 1] ?? start;
            const finish: Coordinate[] =
                body.finish === true ? [last, start] : [];
            const userMetres = metres(waypoints);
            const finishMetres = metres(finish);

            return HttpResponse.json({
                distance_metres: userMetres + finishMetres,
                ascent_metres: 0,
                coordinates: [...waypoints, ...finish.slice(1)],
                user_coordinates: waypoints,
                finish_coordinates: finish,
                user_metres: userMetres,
                finish_metres: finishMetres,
                legs_metres: waypoints
                    .slice(1)
                    .map((point, index) =>
                        metres([waypoints[index] ?? start, point]),
                    ),
            });
        }),

        // PreviewMultisportController (MultisportPreview): the swim as drawn, then one loop per land leg.
        http.post('/routes/multisport', async ({ request }) => {
            const body = await read(request);
            const layout = body.layout === 'chain' ? 'chain' : 'loops';
            let start = {
                longitude: number(body.longitude, westerlo.longitude),
                latitude: number(body.latitude, westerlo.latitude),
            };
            const swim =
                typeof body.swim === 'object' && body.swim !== null
                    ? coordinatesOf(
                          (body.swim as Record<string, unknown>).coordinates,
                      )
                    : [];
            const asked = Array.isArray(body.legs) ? body.legs : [];
            const legs = asked.map((leg: unknown, index) => {
                const record =
                    typeof leg === 'object' && leg !== null
                        ? (leg as Record<string, unknown>)
                        : {};
                const line = loopFrom(
                    start,
                    number(record.distance_metres, 10_000),
                    index,
                    { exact: true },
                );

                if (
                    layout === 'chain' &&
                    typeof record.finish_longitude === 'number' &&
                    typeof record.finish_latitude === 'number'
                ) {
                    start = {
                        longitude: record.finish_longitude,
                        latitude: record.finish_latitude,
                    };
                }

                return {
                    discipline: text(record.sport, 'road'),
                    ...previewOf(line),
                };
            });
            const all = [
                ...(swim.length > 1
                    ? [
                          {
                              discipline: 'swim',
                              distance_metres: metres(swim),
                              ascent_metres: 0,
                              coordinates: swim,
                          },
                      ]
                    : []),
                ...legs,
            ];

            return HttpResponse.json({
                layout,
                distance_metres: all.reduce(
                    (total, leg) => total + leg.distance_metres,
                    0,
                ),
                ascent_metres: all.reduce(
                    (total, leg) => total + leg.ascent_metres,
                    0,
                ),
                legs: all,
            });
        }),

        // RouteDetailsController (RouteDetailsResponse): the climb, surfaces and turns. Stops come apart.
        http.post('/routes/details', async ({ request }) => {
            const body = await read(request);
            const line = coordinatesOf(body.coordinates);

            return line.length < 2
                ? invalid(
                      'The coordinates field must have at least 2 items.',
                      'coordinates',
                  )
                : HttpResponse.json(detailsAlong(line));
        }),

        // RouteStopsController: water, toilets and cafés along the line.
        http.post('/routes/stops', async ({ request }) => {
            const body = await read(request);
            const line = coordinatesOf(body.coordinates);

            return line.length < 2
                ? invalid(
                      'The coordinates field must have at least 2 items.',
                      'coordinates',
                  )
                : HttpResponse.json({ stops: stopsAlong(line) });
        }),

        // ExportFitCourseController: a FIT file named traceway.fit.
        http.post('/routes/fit', async ({ request }) => {
            const body = await read(request);

            return coordinatesOf(body.coordinates).length < 2
                ? invalid(
                      'The coordinates field must have at least 2 items.',
                      'coordinates',
                  )
                : new HttpResponse(fitBytes(), {
                      headers: {
                          'Content-Type': 'application/octet-stream',
                          'Content-Disposition':
                              'attachment; filename="traceway.fit"',
                      },
                  });
        }),

        // ImportRouteController (ImportedRoute): the track in a GPX file, with its name.
        http.post('/routes/import', async ({ request }) => {
            await note(request);
            await delay(latency);
            const form = await request.formData().catch(() => null);
            const file = form?.get('file');
            const gpx = file instanceof File ? await file.text() : '';
            const line = [
                ...gpx.matchAll(/lat="(-?[\d.]+)"\s+lon="(-?[\d.]+)"/g),
            ].map((match): Coordinate => [Number(match[2]), Number(match[1])]);
            const name = gpx.match(/<name>([^<]*)<\/name>/)?.[1] ?? 'Your file';

            return line.length < 2
                ? invalid('The file has no track to follow.', 'file')
                : HttpResponse.json({
                      name,
                      distance_metres: metres(line),
                      ascent_metres: 0,
                      coordinates: line,
                  });
        }),

        // RedoImportedRouteController: the file kept, turned round, or from the pin.
        http.post('/routes/import/redo', async ({ request }) => {
            const body = await read(request);
            const line = coordinatesOf(body.coordinates);
            const start: Coordinate = [
                number(body.longitude, westerlo.longitude),
                number(body.latitude, westerlo.latitude),
            ];
            const ridden =
                body.mode === 'reverse'
                    ? [...line].reverse()
                    : body.mode === 'from_start'
                      ? [start, ...line, start]
                      : line;

            return HttpResponse.json({
                name: text(body.name, 'Your file'),
                distance_metres: metres(ridden),
                ascent_metres: 0,
                coordinates: ridden,
            });
        }),

        // StoreSavedRouteController (SavedRouteLink): the new id and its signed share link.
        http.post('/routes/saved', async ({ request }) => {
            const body = await read(request);
            const line = coordinatesOf(body.coordinates);

            if (line.length < 2) {
                return invalid(
                    'The coordinates field must have at least 2 items.',
                    'coordinates',
                );
            }

            const id = crypto.randomUUID();
            const link = (file: string) =>
                `/share/${id}${file}?expires=1798761600&signature=example`;
            const legs = Array.isArray(body.legs)
                ? body.legs.map((leg: unknown) =>
                      typeof leg === 'object' && leg !== null
                          ? (leg as Record<string, unknown>)
                          : {},
                  )
                : [];
            legLines[id] = legs.map((leg) => coordinatesOf(leg.coordinates));
            library = [
                {
                    id,
                    name: text(body.name, 'Traceway route'),
                    sport: text(body.sport, 'road'),
                    surface: text(body.surface, 'paved'),
                    elevation: text(body.elevation, 'neutral'),
                    roads: text(body.roads, 'quiet'),
                    distance_metres: number(body.distance_metres, metres(line)),
                    ascent_metres: number(body.ascent_metres, 0),
                    url: link(''),
                    fit_url: link('/fit'),
                    gpx_url: link('/gpx'),
                    created_at: new Date().toISOString(),
                    coordinates: line,
                    layout:
                        body.layout === 'loops' || body.layout === 'chain'
                            ? body.layout
                            : null,
                    legs: legs.map((leg, index) => {
                        const discipline = text(leg.discipline, 'road');
                        const land = discipline !== 'swim';

                        return {
                            discipline,
                            distance_metres: number(leg.distance_metres, 0),
                            fit_url: land ? link(`/fit/${index + 1}`) : null,
                            gpx_url: land ? link(`/gpx/${index + 1}`) : null,
                        };
                    }),
                    image_drawn: false,
                    sent_to: [],
                },
                ...library,
            ];

            return HttpResponse.json({ id, url: link('') });
        }),

        // DestroySavedRouteController: the route goes, and the rider is back in the library.
        http.delete('/routes/saved/:route', async ({ request, params }) => {
            await note(request);
            await delay(latency);

            if (find(params.route) === null) {
                return HttpResponse.json(
                    { message: 'Not Found' },
                    { status: 404 },
                );
            }

            library = library.filter((route) => route.id !== params.route);

            return inertiaPage(
                'saved',
                { routes: library },
                {
                    url: storyAddress(),
                    shared: { ...sharedProps(), routeServices: services },
                },
            );
        }),

        // ShowSavedRouteDetailsController: the details kept with a saved route, or one land leg's.
        http.get(
            '/routes/saved/:route/details',
            async ({ request, params }) => {
                await note(request);
                await delay(latency);
                const route = find(params.route);

                if (route === null) {
                    return HttpResponse.json(
                        { message: 'Not Found' },
                        { status: 404 },
                    );
                }

                const leg = Number(
                    new URL(request.url).searchParams.get('leg'),
                );
                const line =
                    route.legs.length === 0
                        ? route.coordinates
                        : (legLines[route.id]?.[leg - 1] ?? []);

                return HttpResponse.json({
                    details:
                        line.length < 2
                            ? null
                            : {
                                  ...detailsAlong(line),
                                  stops: stopsAlong(line),
                              },
                });
            },
        ),

        // FinishTourController: the planner will not show the tour on its own again.
        http.post('/dashboard/tour', async ({ request }) => {
            await note(request);

            return new HttpResponse(null, { status: 204 });
        }),

        // StoreRouteImageController: the picture for the share link is kept.
        http.post('/routes/saved/:route/image', async ({ request, params }) => {
            await note(request);
            if (find(params.route) === null) {
                return HttpResponse.json(
                    { message: 'Not Found' },
                    { status: 404 },
                );
            }

            library = library.map((saved) =>
                saved.id === params.route
                    ? { ...saved, image_drawn: true }
                    : saved,
            );

            return new HttpResponse(null, { status: 204 });
        }),

        // SendRouteToServiceController: sent, or 409 when the service is not connected.
        http.post(
            '/routes/saved/:route/send/:service',
            async ({ request, params }) => {
                await read(request);
                const route = find(params.route);
                const service = services.find(
                    (candidate) => candidate.service === params.service,
                );

                if (route === null || service === undefined) {
                    return HttpResponse.json(
                        { message: 'Not Found' },
                        { status: 404 },
                    );
                }

                if (!service.connected || service.needs_reconnect) {
                    return HttpResponse.json(
                        {
                            message: service.connected
                                ? `${service.label} needs you to connect it again.`
                                : `Connect ${service.label} first.`,
                        },
                        { status: 409 },
                    );
                }

                library = library.map((saved) =>
                    saved.id === route.id &&
                    !saved.sent_to.includes(service.service)
                        ? {
                              ...saved,
                              sent_to: [...saved.sent_to, service.service],
                          }
                        : saved,
                );

                return HttpResponse.json({
                    status: 'sent',
                    service: service.service,
                    message: sendMessages[service.service] ?? 'Sent.',
                    url:
                        service.service === 'ride_with_gps'
                            ? 'https://ridewithgps.com/routes/1'
                            : null,
                });
            },
        ),

        // SavedRouteLibraryController, as Inertia asks for it again after a send (`router.reload`). A reload asks
        // for the page's own address, which in a story is the story's frame, so it is told apart by its headers.
        http.get(/.*/, async ({ request }) => {
            if (
                request.headers.get('X-Inertia') !== 'true' ||
                request.headers.get('X-Inertia-Partial-Component') !== 'saved'
            ) {
                return undefined;
            }

            await note(request);

            return inertiaPage(
                'saved',
                { routes: library },
                {
                    url: storyAddress(),
                    shared: { ...sharedProps(), routeServices: services },
                },
            );
        }),

        // ExportSharedCourseController (CourseDownload): the whole route, or one land leg, as a course file.
        http.get('/share/:route/:format', async ({ request, params }) => {
            const format = params.format;

            // The picture and the embed are not course files.
            if (format !== 'gpx' && format !== 'fit') {
                return passthrough();
            }

            await note(request);
            const route = find(params.route);

            if (route === null) {
                return HttpResponse.json(
                    { message: 'Not Found' },
                    { status: 404 },
                );
            }

            return course(format, route.name, route.coordinates);
        }),
        http.get('/share/:route/:format/:leg', async ({ request, params }) => {
            await note(request);
            const route = find(params.route);
            const format = params.format;
            const number = Number(params.leg);
            const leg = route?.legs[number - 1];
            const line =
                route === null ? [] : (legLines[route.id]?.[number - 1] ?? []);

            if (
                route === null ||
                leg === undefined ||
                leg.discipline === 'swim' ||
                (format !== 'gpx' && format !== 'fit')
            ) {
                return HttpResponse.json(
                    { message: 'Not Found' },
                    { status: 404 },
                );
            }

            return course(
                format,
                legDeviceName(route.name, number, leg.discipline),
                line,
            );
        }),
    ];

    return {
        handlers,
        requests,
        sent: (method, path) =>
            requests.filter(
                (sent) =>
                    sent.method === method &&
                    (typeof path === 'string'
                        ? sent.path.split('?')[0] === path
                        : path.test(sent.path)),
            ),
        library: () => library,
        avoided: () => avoided,
        drawn: () => drawn,
    };
}

/** Five stops along a loop, start and finish included, as a plan keeps them. */
function stopsOf(line: Coordinate[]): Coordinate[] {
    const last = line.length - 1;

    return [0, 0.25, 0.5, 0.75, 1].flatMap((share) => {
        const point = line[Math.round(last * share)];

        return point === undefined ? [] : [point];
    });
}

/** The planner's server with the example library, for a story that only needs answers: `msw.use(...plannerHandlers)`. */
export const plannerHandlers = plannerServer().handlers;

/** What PreviewRouteController answers when no loop came back, for a story about a failed plan. */
export const noLoopFromThatPin = http.post('/routes/preview', async () => {
    await delay(250);

    return HttpResponse.json(
        {
            message:
                'No loop came back from that pin. Try another spot or a different distance.',
        },
        { status: 502 },
    );
});
