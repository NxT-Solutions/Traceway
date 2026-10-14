import {
    type ExpressionSpecification,
    type GeoJSONSource,
    type Map as MapLibreMap,
    Marker as MapLibreMarker,
    type Marker,
} from 'maplibre-gl';

export type Coordinate = [number, number];

export type RoadSurface = 'paved' | 'gravel' | 'dirt' | 'unknown';

export type Turn =
    | 'left'
    | 'right'
    | 'slight_left'
    | 'slight_right'
    | 'sharp_left'
    | 'sharp_right'
    | 'u_turn'
    | 'straight';

export type StopKind = 'water' | 'toilet' | 'cafe';

export type Cue = {
    distance_metres: number;
    longitude: number;
    latitude: number;
    turn: Turn;
    street: string | null;
};

export type Stop = {
    distance_metres: number;
    longitude: number;
    latitude: number;
    kind: StopKind;
    name: string | null;
};

/**
 * How a finished line climbs, what it runs on, where it turns, and where to
 * stop. The profile is pairs of metres from the start and height in metres.
 */
export type RouteDetails = {
    profile: [number, number][];
    ascent_metres: number;
    descent_metres: number;
    surfaces: { surface: RoadSurface; metres: number }[];
    cues: Cue[];
    stops: Stop[];
};

export const surfaceLabels: Record<RoadSurface, string> = {
    paved: 'Paved',
    gravel: 'Gravel',
    dirt: 'Dirt and trail',
    unknown: 'Not known',
};

export const surfaceColours: Record<RoadSurface, string> = {
    paved: '#5b6b73',
    gravel: '#b08d57',
    dirt: '#7a5a3a',
    unknown: '#c9ced1',
};

export const stopLabels: Record<StopKind, string> = {
    water: 'Water',
    toilet: 'Toilet',
    cafe: 'Café',
};

export const stopColours: Record<StopKind, string> = {
    water: '#1d4ed8',
    toilet: '#6d28d9',
    cafe: '#0f766e',
};

export const turnLabels: Record<Turn, string> = {
    left: 'Left',
    right: 'Right',
    slight_left: 'Bear left',
    slight_right: 'Bear right',
    sharp_left: 'Sharp left',
    sharp_right: 'Sharp right',
    u_turn: 'U-turn',
    straight: 'Roundabout',
};

/**
 * Steepness bands, by the rise over a short stretch. A descent reads as flat:
 * it is the climbs a rider wants to see coming.
 */
export const gradeBands = [
    { below: 3, colour: '#3f8f5f', label: 'Under 3%' },
    { below: 6, colour: '#c9a227', label: '3 to 6%' },
    { below: 9, colour: '#d97706', label: '6 to 9%' },
    { below: Number.POSITIVE_INFINITY, colour: '#b91c1c', label: '9% and up' },
] as const;

export function gradeColour(grade: number): string {
    for (const band of gradeBands) {
        if (grade < band.below) {
            return band.colour;
        }
    }

    return '#b91c1c';
}

function isNumberPair(value: unknown): value is [number, number] {
    return (
        Array.isArray(value) &&
        value.length === 2 &&
        typeof value[0] === 'number' &&
        typeof value[1] === 'number'
    );
}

export function isRouteDetails(value: unknown): value is RouteDetails {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        Array.isArray(record.profile) &&
        record.profile.every(isNumberPair) &&
        typeof record.ascent_metres === 'number' &&
        typeof record.descent_metres === 'number' &&
        Array.isArray(record.surfaces) &&
        Array.isArray(record.cues) &&
        Array.isArray(record.stops)
    );
}

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    const token = match?.[1];

    return token === undefined ? '' : decodeURIComponent(token);
}

/**
 * Asks the planner for the details of a finished line. Null when the map
 * server did not answer: the route still works without them.
 */
export async function fetchDetails(
    sport: string,
    coordinates: Coordinate[],
    signal: AbortSignal,
): Promise<RouteDetails | null> {
    const response = await fetch('/routes/details', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ sport, coordinates }),
        signal,
    });

    if (!response.ok) {
        return null;
    }

    const body: unknown = await response.json();

    return isRouteDetails(body) ? body : null;
}

function isStop(value: unknown): value is Stop {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        typeof record.distance_metres === 'number' &&
        typeof record.longitude === 'number' &&
        typeof record.latitude === 'number' &&
        (record.kind === 'water' ||
            record.kind === 'toilet' ||
            record.kind === 'cafe')
    );
}

/**
 * Asks for water, toilets, and cafés along a finished line. They come
 * apart from the climb, since the map server can take longer to find them.
 * Null when it did not answer.
 */
export async function fetchStops(
    sport: string,
    coordinates: Coordinate[],
    signal: AbortSignal,
): Promise<Stop[] | null> {
    const response = await fetch('/routes/stops', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ sport, coordinates }),
        signal,
    });

    if (!response.ok) {
        return null;
    }

    const body: unknown = await response.json();
    const stops =
        typeof body === 'object' && body !== null && 'stops' in body
            ? body.stops
            : null;

    return Array.isArray(stops) && stops.every(isStop) ? stops : null;
}

/**
 * The height of the ground after so many metres, between the two samples
 * either side.
 */
export function elevationAt(
    profile: [number, number][],
    metres: number,
): number | null {
    const first = profile[0];
    const last = profile[profile.length - 1];

    if (first === undefined || last === undefined) {
        return null;
    }

    if (metres <= first[0]) {
        return first[1];
    }

    if (metres >= last[0]) {
        return last[1];
    }

    let low = 0;
    let high = profile.length - 1;

    while (high - low > 1) {
        const middle = Math.floor((low + high) / 2);

        if ((profile[middle]?.[0] ?? 0) <= metres) {
            low = middle;
        } else {
            high = middle;
        }
    }

    const from = profile[low] ?? first;
    const to = profile[high] ?? last;
    const span = to[0] - from[0];
    const share = span > 0 ? (metres - from[0]) / span : 0;

    return from[1] + (to[1] - from[1]) * share;
}

/**
 * The steepness of each stretch of the profile, as a percentage, over about
 * a hundred metres, so one noisy sample does not paint a wall.
 */
export function grades(
    profile: [number, number][],
): { from: number; to: number; grade: number }[] {
    const total = profile[profile.length - 1]?.[0] ?? 0;
    const stretch = Math.max(100, total / 300);
    const result: { from: number; to: number; grade: number }[] = [];

    for (let start = 0; start < total; start += stretch) {
        const end = Math.min(total, start + stretch);
        const rise =
            (elevationAt(profile, end) ?? 0) -
            (elevationAt(profile, start) ?? 0);
        result.push({
            from: start,
            to: end,
            grade: end > start ? (rise / (end - start)) * 100 : 0,
        });
    }

    return result;
}

function metresBetween(from: Coordinate, to: Coordinate): number {
    const radius = 6_371_000;
    const lat1 = (from[1] * Math.PI) / 180;
    const lat2 = (to[1] * Math.PI) / 180;
    const dLat = lat2 - lat1;
    const dLon = ((to[0] - from[0]) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;

    return 2 * radius * Math.asin(Math.min(1, Math.sqrt(a)));
}

/**
 * The point on the line after so many metres.
 */
export function pointAlong(
    coordinates: Coordinate[],
    metres: number,
): Coordinate | null {
    let travelled = 0;

    for (let index = 1; index < coordinates.length; index++) {
        const from = coordinates[index - 1];
        const to = coordinates[index];

        if (from === undefined || to === undefined) {
            continue;
        }

        const hop = metresBetween(from, to);

        if (travelled + hop >= metres) {
            const share = hop > 0 ? (metres - travelled) / hop : 0;

            return [
                from[0] + (to[0] - from[0]) * share,
                from[1] + (to[1] - from[1]) * share,
            ];
        }

        travelled += hop;
    }

    return coordinates[coordinates.length - 1] ?? null;
}

function xml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

const stopSymbols: Record<StopKind, string> = {
    water: 'Drinking Water',
    toilet: 'Restroom',
    cafe: 'Restaurant',
};

/**
 * The track points of one line. With details, each has its height.
 */
export function gpxTrackPoints(
    coordinates: Coordinate[],
    details: RouteDetails | null,
): string {
    let travelled = 0;
    let previous: Coordinate | null = null;

    return coordinates
        .map((point) => {
            if (previous !== null) {
                travelled += metresBetween(previous, point);
            }

            previous = point;
            const height =
                details === null || details.profile.length === 0
                    ? null
                    : elevationAt(details.profile, travelled);

            return `<trkpt lat="${point[1]}" lon="${point[0]}">${height === null ? '' : `<ele>${Math.round(height * 10) / 10}</ele>`}</trkpt>`;
        })
        .join('');
}

/**
 * A waypoint for each stop on the line.
 */
export function gpxStopWaypoints(details: RouteDetails | null): string {
    return (details?.stops ?? [])
        .map(
            (stop) =>
                `<wpt lat="${stop.latitude}" lon="${stop.longitude}"><name>${xml(stop.name ?? stopLabels[stop.kind])}</name><sym>${stopSymbols[stop.kind]}</sym><type>${stop.kind}</type></wpt>`,
        )
        .join('');
}

/**
 * A GPX track for one line. With details, each point has its height and
 * each stop is a waypoint, as in the course files Traceway serves.
 */
export function routeGpx(
    name: string,
    coordinates: Coordinate[],
    details: RouteDetails | null,
): string {
    return `<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" creator="Traceway">${gpxStopWaypoints(details)}<trk><name>${xml(name)}</name><trkseg>${gpxTrackPoints(coordinates, details)}</trkseg></trk></gpx>`;
}

const detailSources = ['route-steepness', 'route-stops', 'route-hover'];

function stopIcon(kind: StopKind): ImageData {
    const size = 44;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const context = canvas.getContext('2d');

    if (context === null) {
        return new ImageData(size, size);
    }

    context.fillStyle = '#ffffff';
    context.beginPath();
    context.arc(size / 2, size / 2, size / 2 - 1, 0, Math.PI * 2);
    context.fill();
    context.fillStyle = stopColours[kind];
    context.beginPath();
    context.arc(size / 2, size / 2, size / 2 - 5, 0, Math.PI * 2);
    context.fill();
    context.fillStyle = '#ffffff';
    context.strokeStyle = '#ffffff';
    context.lineWidth = 2.5;

    if (kind === 'water') {
        context.beginPath();
        context.moveTo(22, 11);
        context.bezierCurveTo(22, 11, 13, 21, 13, 26);
        context.arc(22, 26, 9, Math.PI, 0, true);
        context.bezierCurveTo(31, 21, 22, 11, 22, 11);
        context.fill();
    } else if (kind === 'cafe') {
        context.fillRect(12, 17, 15, 12);
        context.beginPath();
        context.arc(29, 23, 4, -Math.PI / 2, Math.PI / 2);
        context.stroke();
        context.fillRect(11, 31, 18, 2.5);
    } else {
        context.font = 'bold 15px system-ui, sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText('WC', size / 2, size / 2 + 1);
    }

    return context.getImageData(0, 0, size, size);
}

/**
 * Adds the steepness line, the stop pins, and the dot that follows the
 * profile chart. They sit above the route so a stop is never hidden by it.
 */
export function addDetailLayers(map: MapLibreMap): void {
    if (map.getSource('route-steepness') === undefined) {
        map.addSource('route-steepness', {
            type: 'geojson',
            lineMetrics: true,
            data: {
                type: 'Feature',
                properties: {},
                geometry: { type: 'LineString', coordinates: [] },
            },
        });
    }

    if (map.getLayer('route-steepness') === undefined) {
        map.addLayer(
            {
                id: 'route-steepness',
                type: 'line',
                source: 'route-steepness',
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round',
                    visibility: 'none',
                },
                paint: { 'line-width': 6, 'line-color': '#3f8f5f' },
            },
            map.getLayer('route-arrows') === undefined
                ? undefined
                : 'route-arrows',
        );
    }

    for (const kind of Object.keys(stopColours) as StopKind[]) {
        if (!map.hasImage(`stop-${kind}`)) {
            map.addImage(`stop-${kind}`, stopIcon(kind), { pixelRatio: 2 });
        }
    }

    if (map.getSource('route-stops') === undefined) {
        map.addSource('route-stops', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: [] },
        });
    }

    if (map.getLayer('route-stops') === undefined) {
        map.addLayer({
            id: 'route-stops',
            type: 'symbol',
            source: 'route-stops',
            layout: {
                'icon-image': ['concat', 'stop-', ['get', 'kind']],
                'icon-allow-overlap': true,
                'icon-size': [
                    'interpolate',
                    ['linear'],
                    ['zoom'],
                    9,
                    0.7,
                    14,
                    1,
                ],
            },
        });
    }

    if (map.getSource('route-hover') === undefined) {
        map.addSource('route-hover', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: [] },
        });
    }

    // The dot that follows the chart sits under the stop icons, so pointing
    // at the chart never hides one.
    if (map.getLayer('route-hover') === undefined) {
        map.addLayer(
            {
                id: 'route-hover',
                type: 'circle',
                source: 'route-hover',
                paint: {
                    'circle-radius': 7,
                    'circle-color': '#16303a',
                    'circle-stroke-color': '#ffffff',
                    'circle-stroke-width': 3,
                },
            },
            'route-stops',
        );
    }
}

/**
 * The line-progress colour stops for a line painted by steepness. Stretches
 * of one colour join, so a long flat road is one stop, not hundreds.
 */
function steepnessPaint(profile: [number, number][]): ExpressionSpecification {
    const stretches = grades(profile);
    const total = profile[profile.length - 1]?.[0] ?? 0;
    const first = stretches[0];

    if (first === undefined || total <= 0) {
        return ['step', ['line-progress'], gradeBands[0].colour];
    }

    const expression: unknown[] = [
        'step',
        ['line-progress'],
        gradeColour(first.grade),
    ];
    let current = gradeColour(first.grade);
    let lastStop = 0;

    for (const stretch of stretches.slice(1)) {
        const colour = gradeColour(stretch.grade);
        const progress = stretch.from / total;

        if (colour !== current && progress > lastStop) {
            expression.push(progress, colour);
            current = colour;
            lastStop = progress;
        }
    }

    return expression as ExpressionSpecification;
}

/**
 * Shows the details of the line on the map: its stops, and its steepness
 * when the rider asks for it. Without details, the layers are empty.
 */
export function syncDetailLayers(
    map: MapLibreMap,
    coordinates: Coordinate[],
    details: RouteDetails | null,
    steepness: boolean,
): void {
    if (detailSources.some((id) => map.getSource(id) === undefined)) {
        return;
    }

    const showSteepness =
        steepness && details !== null && details.profile.length > 1;

    (map.getSource('route-steepness') as GeoJSONSource).setData({
        type: 'Feature',
        properties: {},
        geometry: {
            type: 'LineString',
            coordinates: showSteepness ? coordinates : [],
        },
    });
    map.setLayoutProperty(
        'route-steepness',
        'visibility',
        showSteepness ? 'visible' : 'none',
    );

    if (showSteepness) {
        map.setPaintProperty(
            'route-steepness',
            'line-gradient',
            steepnessPaint(details.profile),
        );
    }

    (map.getSource('route-stops') as GeoJSONSource).setData({
        type: 'FeatureCollection',
        features: (details?.stops ?? []).map((stop) => ({
            type: 'Feature' as const,
            properties: { kind: stop.kind, name: stop.name ?? '' },
            geometry: {
                type: 'Point' as const,
                coordinates: [stop.longitude, stop.latitude],
            },
        })),
    });
}

/**
 * Moves the dot that marks where the rider is looking on the profile, or
 * hides it.
 */
export function syncHoverPoint(
    map: MapLibreMap,
    point: Coordinate | null,
): void {
    const source = map.getSource('route-hover');

    if (source === undefined || source.type !== 'geojson') {
        return;
    }

    (source as GeoJSONSource).setData({
        type: 'FeatureCollection',
        features:
            point === null
                ? []
                : [
                      {
                          type: 'Feature',
                          properties: {},
                          geometry: { type: 'Point', coordinates: point },
                      },
                  ],
    });
}

const chosenMarkers = new WeakMap<MapLibreMap, Marker>();

/**
 * Marks the stop or turn the rider chose with a ring that pulses around it.
 * The middle stays open, so the stop's own icon shows through. Null takes
 * the ring away.
 */
export function markChosen(map: MapLibreMap, point: Coordinate | null): void {
    const marker = chosenMarkers.get(map);

    if (point === null) {
        marker?.remove();
        chosenMarkers.delete(map);

        return;
    }

    if (marker !== undefined) {
        marker.setLngLat(point);

        return;
    }

    const element = document.createElement('div');
    element.className = 'tw-chosen';
    element.setAttribute('aria-hidden', 'true');
    chosenMarkers.set(
        map,
        new MapLibreMarker({ element }).setLngLat(point).addTo(map),
    );
}
