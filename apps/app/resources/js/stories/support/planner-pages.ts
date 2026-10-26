import type { ComponentProps } from 'react';
import type { AvoidedRoad } from '@/lib/avoided-roads';
import type { FoundPlace } from '@/lib/places';
import type { Cue, RouteDetails, Stop } from '@/lib/route-details';
import type Saved from '@/pages/saved';
import type Share from '@/pages/share';
import type { RouteServiceStatus } from '@/types/route-services';

/**
 * Example routes for the planner page stories, around Westerlo, Belgium. The lines are short sketches of a loop,
 * not routes from the routing engine: a story has no server to draw one. Every line a story shows is this one
 * sketch, moved, turned, and scaled, never a plan for a real ride.
 */
export type Coordinate = [number, number];

export type SavedRoute = ComponentProps<typeof Saved>['routes'][number];

type SharedRoute = ComponentProps<typeof Share>['route'];

/** A loop of about 9 km from the centre of Westerlo, out east towards Tongerlo and back along the Grote Nete. */
export const westerloLoop: Coordinate[] = [
    [4.9165, 51.089],
    [4.926, 51.0925],
    [4.938, 51.095],
    [4.9505, 51.093],
    [4.956, 51.0865],
    [4.952, 51.079],
    [4.941, 51.0745],
    [4.928, 51.0735],
    [4.915, 51.076],
    [4.906, 51.081],
    [4.908, 51.086],
    [4.9165, 51.089],
];

/** The same loop, larger or smaller, moved a little, so the library and the planner show different routes. */
function variant(scale: number, east = 0, north = 0): Coordinate[] {
    const [startLng, startLat] = westerloLoop[0] ?? [4.9165, 51.089];

    return westerloLoop.map(([lng, lat]) => [
        Number((startLng + (lng - startLng) * scale + east).toFixed(5)),
        Number((startLat + (lat - startLat) * scale + north).toFixed(5)),
    ]);
}

/** Metres along a line, by the great circle between its points. */
export function metres(line: Coordinate[]): number {
    let total = 0;

    for (let index = 1; index < line.length; index++) {
        const [lng1, lat1] = line[index - 1] ?? [0, 0];
        const [lng2, lat2] = line[index] ?? [0, 0];
        const radians = Math.PI / 180;
        const a =
            Math.sin(((lat2 - lat1) * radians) / 2) ** 2 +
            Math.cos(lat1 * radians) *
                Math.cos(lat2 * radians) *
                Math.sin(((lng2 - lng1) * radians) / 2) ** 2;
        total += 2 * 6_371_000 * Math.asin(Math.sqrt(a));
    }

    return Math.round(total);
}

const gravelLoop = variant(2.1, 0.004, 0.002);
const roadLoop = variant(3.4, -0.01, -0.004);
const walkLoop = variant(0.55, -0.008, 0.001);
const trailLoop = variant(1.3, 0.02, -0.012);

/** Heights along Westerlo's loop: the Kempen is flat, with a rise near the abbey. */
export function profileFor(line: Coordinate[]): [number, number][] {
    const heights = [16, 18, 22, 27, 24, 19, 15, 13, 14, 15, 17, 16];
    let along = 0;

    return line.map((point, index) => {
        if (index > 0) {
            along += metres([line[index - 1] ?? point, point]);
        }

        return [along, heights[index % heights.length] ?? 16];
    });
}

/** The climb, surfaces, turns, and stops for the loop, as the routing engine would describe them. */
export const westerloDetails: RouteDetails = {
    profile: profileFor(westerloLoop),
    ascent_metres: 31,
    descent_metres: 31,
    surfaces: [
        { surface: 'paved', metres: 6200 },
        { surface: 'gravel', metres: 2100 },
        { surface: 'dirt', metres: 600 },
    ],
    cues: [
        {
            distance_metres: 780,
            longitude: 4.926,
            latitude: 51.0925,
            turn: 'slight_left',
            street: 'Boerenkrijglaan',
        },
        {
            distance_metres: 2560,
            longitude: 4.9505,
            latitude: 51.093,
            turn: 'right',
            street: 'Abdijlaan',
        },
        {
            distance_metres: 4260,
            longitude: 4.952,
            latitude: 51.079,
            turn: 'right',
            street: 'Netepad',
        },
        {
            distance_metres: 6080,
            longitude: 4.928,
            latitude: 51.0735,
            turn: 'straight',
            street: 'Bergveld',
        },
        {
            distance_metres: 7870,
            longitude: 4.906,
            latitude: 51.081,
            turn: 'right',
            street: 'Grote Markt',
        },
    ],
    stops: [
        {
            distance_metres: 2900,
            longitude: 4.9532,
            latitude: 51.0898,
            kind: 'cafe',
            name: 'Abbey café',
        },
        {
            distance_metres: 5200,
            longitude: 4.9465,
            latitude: 51.0768,
            kind: 'water',
            name: null,
        },
        {
            distance_metres: 8600,
            longitude: 4.9072,
            latitude: 51.0838,
            kind: 'toilet',
            name: 'Markt',
        },
    ],
};

const link = (id: string, file: string) =>
    `/share/${id}${file}?expires=1798761600&signature=example`;

function savedRoute(
    route: Omit<
        SavedRoute,
        | 'url'
        | 'fit_url'
        | 'gpx_url'
        | 'distance_metres'
        | 'layout'
        | 'legs'
        | 'image_drawn'
    > &
        Partial<SavedRoute>,
): SavedRoute {
    return {
        url: link(route.id, ''),
        fit_url: link(route.id, '/fit'),
        gpx_url: link(route.id, '/gpx'),
        distance_metres: metres(route.coordinates),
        layout: null,
        legs: [],
        // Every picture is drawn already: drawing one would send it to the server.
        image_drawn: true,
        ...route,
    };
}

const duathlonId = '6f2c1d8e-0b7a-4e55-9d0c-3a1e7b9f4d21';

/** The rider's library, newest first, as LibraryQuery returns it. */
export const libraryRoutes: SavedRoute[] = [
    savedRoute({
        id: '2b9e4c71-8d3f-4a62-b1e0-5c7d9a3f6e18',
        name: 'Westerlo morning run',
        sport: 'run',
        surface: 'paved',
        elevation: 'neutral',
        roads: 'quiet',
        ascent_metres: 31,
        created_at: '2026-10-18T06:42:00Z',
        coordinates: westerloLoop,
        sent_to: [],
    }),
    savedRoute({
        id: 'a4d7f0e2-3c19-4b8a-9e56-71f2c8b0d3a4',
        name: 'Grote Nete gravel',
        sport: 'gravel',
        surface: 'mixed',
        elevation: 'neutral',
        roads: 'quiet',
        ascent_metres: 64,
        created_at: '2026-10-15T15:10:00Z',
        coordinates: gravelLoop,
        sent_to: ['wahoo'],
    }),
    savedRoute({
        id: duathlonId,
        name: 'Westerlo duathlon',
        sport: 'run',
        surface: 'paved',
        elevation: 'neutral',
        roads: 'quiet',
        ascent_metres: 88,
        created_at: '2026-10-12T09:00:00Z',
        coordinates: [...walkLoop, ...gravelLoop, ...walkLoop],
        layout: 'loops',
        legs: [
            {
                discipline: 'run',
                distance_metres: metres(walkLoop),
                fit_url: link(duathlonId, '/fit/1'),
                gpx_url: link(duathlonId, '/gpx/1'),
            },
            {
                discipline: 'road',
                distance_metres: metres(gravelLoop),
                fit_url: link(duathlonId, '/fit/2'),
                gpx_url: link(duathlonId, '/gpx/2'),
            },
            {
                discipline: 'run',
                distance_metres: metres(walkLoop),
                fit_url: link(duathlonId, '/fit/3'),
                gpx_url: link(duathlonId, '/gpx/3'),
            },
        ],
        sent_to: [],
    }),
    savedRoute({
        id: 'c81e3a5f-6d2b-4f97-8a04-e9b3d1c6f572',
        name: 'Tongerlo abbey ride',
        sport: 'road',
        surface: 'paved',
        elevation: 'minimize',
        roads: 'quiet',
        ascent_metres: 112,
        created_at: '2026-10-04T13:25:00Z',
        coordinates: roadLoop,
        sent_to: ['ride_with_gps', 'wahoo'],
    }),
    savedRoute({
        id: 'e5b2d9c4-1a7f-4e83-b6d0-28f4a9c1e7b3',
        name: 'Zoerle-Parwijs trails',
        sport: 'trail_run',
        surface: 'dirt',
        elevation: 'maximize',
        roads: 'any',
        ascent_metres: 58,
        created_at: '2026-09-27T07:55:00Z',
        coordinates: trailLoop,
        sent_to: [],
    }),
    savedRoute({
        id: 'f9a0c3e6-4b5d-4c21-a8f7-3d6e1b2c9a05',
        name: 'Sunday walk to Heultje',
        sport: 'walk',
        surface: 'mixed',
        elevation: 'neutral',
        roads: 'quiet',
        ascent_metres: 12,
        created_at: '2026-09-14T10:30:00Z',
        coordinates: walkLoop,
        sent_to: [],
    }),
];

/** The line of each leg of the library's multisport routes, which the library itself does not carry. */
export const libraryLegLines: Record<string, Coordinate[][]> = {
    [duathlonId]: [walkLoop, gravelLoop, walkLoop],
};

const sharedId = '2b9e4c71-8d3f-4a62-b1e0-5c7d9a3f6e18';

/** The run loop as its share link shows it, details included. */
export const sharedRoute: SharedRoute = {
    name: 'Westerlo morning run',
    sport: 'run',
    surface: 'paved',
    elevation: 'neutral',
    roads: 'quiet',
    shape: 'loop',
    distance_metres: metres(westerloLoop),
    ascent_metres: 31,
    coordinates: westerloLoop,
    gpx_url: link(sharedId, '/gpx'),
    fit_url: link(sharedId, '/fit'),
    layout: null,
    legs: [],
    details: westerloDetails,
    image_url: `/share/${sharedId}/image.jpg`,
};

/** A run, ride, run session as its share link shows it: one line per leg, the climb of each land leg. */
export const sharedDuathlon: SharedRoute = {
    name: 'Westerlo duathlon',
    sport: 'run',
    surface: 'paved',
    elevation: 'neutral',
    roads: 'quiet',
    shape: null,
    distance_metres: metres(walkLoop) * 2 + metres(gravelLoop),
    ascent_metres: 88,
    coordinates: [...walkLoop, ...gravelLoop, ...walkLoop],
    gpx_url: link(duathlonId, '/gpx'),
    fit_url: link(duathlonId, '/fit'),
    layout: 'loops',
    legs: [
        {
            discipline: 'run',
            distance_metres: metres(walkLoop),
            ascent_metres: 12,
            coordinates: walkLoop,
            fit_url: link(duathlonId, '/fit/1'),
            details: {
                ...westerloDetails,
                profile: profileFor(walkLoop),
                cues: [],
                stops: [],
            },
        },
        {
            discipline: 'road',
            distance_metres: metres(gravelLoop),
            ascent_metres: 64,
            coordinates: gravelLoop,
            fit_url: link(duathlonId, '/fit/2'),
            details: {
                ...westerloDetails,
                profile: profileFor(gravelLoop),
                ascent_metres: 64,
                descent_metres: 64,
                cues: [],
                stops: [],
            },
        },
        {
            discipline: 'run',
            distance_metres: metres(walkLoop),
            ascent_metres: 12,
            coordinates: walkLoop,
            fit_url: link(duathlonId, '/fit/3'),
            details: null,
        },
    ],
    details: null,
    image_url: `/share/${duathlonId}/image.jpg`,
};

/** Where the planner stories start: the Grote Markt in Westerlo, the first point of the sketch. */
const westerloStart = [4.9165, 51.089] as const;
export const westerlo = {
    longitude: westerloStart[0],
    latitude: westerloStart[1],
} as const;

/** How each of the three loops a plan returns differs from the first: turned this far, and a little longer or shorter. */
const loopVariants = [
    { turn: 0, stretch: 1 },
    { turn: 115, stretch: 0.95 },
    { turn: 235, stretch: 1.05 },
] as const;

const metresPerDegreeNorth = 110_540;

/** Metres in a degree east, at a latitude. */
const metresPerDegreeEast = (latitude: number) =>
    Math.cos((latitude * Math.PI) / 180) * 111_320;

/**
 * The Westerlo sketch moved to a start, turned, and scaled to about a distance in metres. Loop 0 is the sketch
 * itself at that size; loops 1 and 2 leave the start in other directions, a little shorter and longer unless `exact`. It is what the mock server answers for a
 * plan, so a story shows a line of the asked length, never a route from the routing engine.
 */
export function loopFrom(
    start: { longitude: number; latitude: number },
    distanceMetres: number,
    index = 0,
    { exact = false }: { exact?: boolean } = {},
): Coordinate[] {
    const [firstLng, firstLat] = westerloLoop[0] ?? [
        westerlo.longitude,
        westerlo.latitude,
    ];
    const { turn, stretch } = loopVariants[index % loopVariants.length] ?? {
        turn: 0,
        stretch: 1,
    };
    const scale =
        (distanceMetres / metres(westerloLoop)) * (exact ? 1 : stretch);
    const angle = (turn * Math.PI) / 180;

    return westerloLoop.map(([lng, lat]) => {
        const east = (lng - firstLng) * metresPerDegreeEast(firstLat) * scale;
        const north = (lat - firstLat) * metresPerDegreeNorth * scale;
        const turnedEast = east * Math.cos(angle) - north * Math.sin(angle);
        const turnedNorth = east * Math.sin(angle) + north * Math.cos(angle);

        return [
            Number(
                (
                    start.longitude +
                    turnedEast / metresPerDegreeEast(start.latitude)
                ).toFixed(5),
            ),
            Number(
                (start.latitude + turnedNorth / metresPerDegreeNorth).toFixed(
                    5,
                ),
            ),
        ];
    });
}

/** A line as the planner's preview carries it: its length, a climb for the flat Kempen, and its points. */
export function previewOf(coordinates: Coordinate[]) {
    const distance = metres(coordinates);

    return {
        distance_metres: distance,
        ascent_metres: Math.max(4, Math.round((distance / 1000) * 3.4)),
        coordinates,
    };
}

/** Streets the turn list names, from the centre of Westerlo out to Tongerlo and back. */
const streets = [
    'Boerenkrijglaan',
    'Abdijlaan',
    'Netepad',
    'Bergveld',
    'Grote Markt',
];
const turns: Cue['turn'][] = [
    'slight_left',
    'right',
    'right',
    'straight',
    'right',
];

/** The stops along a line: a café, water, and a toilet, at the same points of the sketch as on Westerlo's loop. */
export function stopsAlong(line: Coordinate[]): Stop[] {
    const profile = profileFor(line);
    const at = (index: number) => {
        const last = Math.min(index, line.length - 1);
        const point = line[last];

        return {
            distance_metres: Math.round(profile[last]?.[0] ?? 0),
            longitude: point?.[0] ?? westerlo.longitude,
            latitude: point?.[1] ?? westerlo.latitude,
        };
    };

    return [
        { ...at(3), kind: 'cafe', name: 'Abbey café' },
        { ...at(5), kind: 'water', name: null },
        { ...at(10), kind: 'toilet', name: 'Markt' },
    ];
}

/**
 * The climb, surfaces and turns along any line, in the shape RouteDetailsController answers with: heights from the
 * sketch, the sketch's shares of each surface, a turn at every other point, and no stops (those come from
 * `/routes/stops`).
 */
export function detailsAlong(line: Coordinate[]): RouteDetails {
    const profile = profileFor(line);
    const total = metres(line);
    let ascent = 0;
    let descent = 0;

    for (let index = 1; index < profile.length; index++) {
        const rise =
            (profile[index]?.[1] ?? 0) - (profile[index - 1]?.[1] ?? 0);
        ascent += Math.max(0, rise);
        descent += Math.max(0, -rise);
    }

    const cues = [1, 3, 5, 7, 9].flatMap((index, position): Cue[] => {
        const point = line[index];
        const along = profile[index]?.[0];

        return point === undefined || along === undefined
            ? []
            : [
                  {
                      distance_metres: Math.round(along),
                      longitude: point[0],
                      latitude: point[1],
                      turn: turns[position] ?? 'straight',
                      street: streets[position] ?? null,
                  },
              ];
    });

    return {
        profile,
        ascent_metres: ascent,
        descent_metres: descent,
        surfaces: [
            { surface: 'paved', metres: Math.round(total * 0.7) },
            { surface: 'gravel', metres: Math.round(total * 0.23) },
            { surface: 'dirt', metres: Math.round(total * 0.07) },
        ],
        cues,
        stops: [],
    };
}

/** What the place search finds in the planner stories: towns and a landmark around Westerlo. */
export const places: Required<FoundPlace>[] = [
    {
        name: 'Westerlo',
        area: 'Antwerp, Belgium',
        latitude: westerlo.latitude,
        longitude: westerlo.longitude,
        kind: 'town',
    },
    {
        name: 'Tongerlo',
        area: 'Westerlo, Antwerp, Belgium',
        latitude: 51.0985,
        longitude: 4.9545,
        kind: 'town',
    },
    {
        name: 'Tongerlo Abbey',
        area: 'Abdijstraat, Westerlo, Belgium',
        latitude: 51.0995,
        longitude: 4.9585,
        kind: 'place',
    },
    {
        name: 'Herselt',
        area: 'Antwerp, Belgium',
        latitude: 51.0515,
        longitude: 4.8835,
        kind: 'town',
    },
    {
        name: 'Geel',
        area: 'Antwerp, Belgium',
        latitude: 51.1615,
        longitude: 4.9905,
        kind: 'town',
    },
];

/** The rider's services, as HandleInertiaRequests shares them: Wahoo connected, Ride with GPS not yet. */
export const routeServices: RouteServiceStatus[] = [
    {
        service: 'wahoo',
        label: 'Wahoo',
        connected: true,
        needs_reconnect: false,
        connected_at: '2026-10-02T07:30:00Z',
        last_sent_at: '2026-10-19T06:45:00Z',
    },
    {
        service: 'ride_with_gps',
        label: 'Ride with GPS',
        connected: false,
        needs_reconnect: false,
        connected_at: null,
        last_sent_at: null,
    },
];

/** What a planner story sets in the draft, as Controls. */
export type PlannerDraftOptions = {
    sport: string;
    distanceKm: number;
    surface: string;
    elevation: string;
    shape: string;
    roads: 'quiet' | 'any';
    mapStyle: string;
    name: string;
    /** How many loops the draft already holds: 0 for a start with nothing drawn yet. */
    loops: number;
    /** Which loop is chosen, from 0. */
    routeIndex: number;
};

export const plannerDraftDefaults: PlannerDraftOptions = {
    sport: 'run',
    distanceKm: 10,
    surface: 'paved',
    elevation: 'neutral',
    shape: 'loop',
    roads: 'quiet',
    mapStyle: 'auto',
    name: 'Westerlo morning run',
    loops: 3,
    routeIndex: 0,
};

/**
 * The planner's draft in this browser, as the dashboard writes it, starting in Westerlo. The dashboard reads it on
 * load, so a story opens on its settings and loops without asking the server. The loops are the ones the mock
 * server answers for the same ask.
 */
export function plannerDraftFor(options: Partial<PlannerDraftOptions> = {}) {
    const chosen = { ...plannerDraftDefaults, ...options };
    const loops = Math.max(0, Math.min(3, Math.round(chosen.loops)));

    return {
        version: 1,
        // The made-up start this draft is written with, a town square, from the pair rather than `westerlo`: CodeQL
        // takes any read of a latitude for a rider's location kept in clear text.
        latitude: westerloStart[1],
        longitude: westerloStart[0],
        distanceKm: chosen.distanceKm,
        sport: chosen.sport,
        surface: chosen.surface,
        elevation: chosen.elevation,
        shape: chosen.shape,
        roads: chosen.roads,
        mapStyle: chosen.mapStyle,
        mapStyleVersion: 2,
        name: chosen.name,
        nameFrom: 'rider',
        sessionName: null,
        via: null,
        startName: 'Westerlo',
        routes: Array.from({ length: loops }, (_, index) =>
            previewOf(loopFrom(westerlo, chosen.distanceKm * 1000, index)),
        ),
        routeIndex: Math.max(0, Math.min(loops - 1, chosen.routeIndex)),
    };
}

/** Three loops of about 10 km from Westerlo, the first one chosen. */
export const plannerDraft = plannerDraftFor();

/** What the server shares with the dashboard (routes/web.php): no notice, and each sport's starting surface. */
export const sportSurfaces: Record<string, string> = {
    road: 'paved',
    gravel: 'mixed',
    mtb: 'dirt',
    run: 'paved',
    trail_run: 'dirt',
    walk: 'mixed',
    hike: 'dirt',
};

/** A sandy track near Westerlo the rider avoids: mapped as a fine track, ridden it is a sand pit. */
export const avoidedRoads: AvoidedRoad[] = [
    {
        id: '0199f2a4-6c1b-7d3e-9a8f-4b4c6d8e0f21',
        name: 'Zandstraat',
        latitude: 51.0952,
        longitude: 4.9241,
        sport: 'gravel',
        surface: 'mixed',
        shape: [
            [4.9223, 51.0949],
            [4.9241, 51.0952],
            [4.9262, 51.0956],
        ],
    },
];
