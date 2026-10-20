import { delay, type HttpHandler, HttpResponse, http, passthrough } from 'msw';
// Types `context.msw`, so a story can `beforeEach({ msw }) { msw.use(…) }`.
import type {} from 'msw-storybook-addon/csf3';
import { legDeviceName } from '@/lib/multisport';
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
};

type Options = {
    /** The routes the rider saved before, newest first. */
    library?: SavedRoute[];
    /** The services the rider connected, as HandleInertiaRequests shares them. */
    services?: RouteServiceStatus[];
    /** How long each answer takes, in milliseconds, so a story shows its busy states. */
    latency?: number;
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
            const routes = [0, 1, 2].map((index) =>
                previewOf(loopFrom(start, distance, index)),
            );
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
    };
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
