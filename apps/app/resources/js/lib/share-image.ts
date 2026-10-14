import * as maplibregl from 'maplibre-gl';
import { darkMapStyle } from '@/lib/maplibre';

type Coordinate = [number, number];

/**
 * What the picture for a shared link shows: the route on the dark map, the
 * Traceway mark, the route name, and three facts, laid out like the picture
 * the home page shares.
 */
export type ShareImageRoute = {
    name: string;
    headline: string;
    lines: { coordinates: Coordinate[]; color?: string | null }[];
    facts: { label: string; value: string }[];
};

const width = 1200;
const height = 630;
const ink = '#071f20';
const paper = '#f7f5ec';
const quiet = 'rgba(247, 245, 236, 0.62)';
const chartreuse = '#97cb46';
const font = "Outfit, 'Avenir Next', 'Segoe UI', system-ui, sans-serif";

const rides: Record<string, string> = {
    road: 'road ride',
    gravel: 'gravel ride',
    mtb: 'mountain bike ride',
    run: 'run',
    trail_run: 'trail run',
    walk: 'walk',
    hike: 'hike',
    swim: 'swim',
};

const surfaces: Record<string, string> = {
    paved: 'Paved',
    mixed: 'Mixed',
    gravel: 'Gravel',
    dirt: 'Dirt and trail',
};

function kilometres(metres: number): string {
    return `${(metres / 1000).toFixed(1)} km`;
}

/**
 * The headline and the facts of a route, the same words the link's text uses.
 */
export function shareImageRoute(route: {
    name: string;
    sport: string;
    surface: string;
    distance_metres: number;
    ascent_metres: number;
    coordinates: Coordinate[];
    legs?: {
        discipline: string;
        distance_metres: number;
        coordinates?: Coordinate[];
        color?: string | null;
    }[];
}): ShareImageRoute {
    const legs = route.legs ?? [];
    const multisport = legs.length > 0;
    const drawnLegs = legs.filter((leg) => (leg.coordinates?.length ?? 0) > 1);

    return {
        name: route.name.trim() === '' ? 'A route' : route.name.trim(),
        headline: multisport
            ? legs
                  .map((leg) => rides[leg.discipline] ?? leg.discipline)
                  .join(' · ')
            : `${kilometres(route.distance_metres)} ${rides[route.sport] ?? 'route'}`,
        lines:
            drawnLegs.length > 0
                ? drawnLegs.map((leg) => ({
                      coordinates: leg.coordinates ?? [],
                      color: leg.color,
                  }))
                : [{ coordinates: route.coordinates }],
        facts: [
            { label: 'Distance', value: kilometres(route.distance_metres) },
            {
                label: 'Climbing',
                value:
                    route.ascent_metres > 0
                        ? `${route.ascent_metres} m`
                        : 'Flat',
            },
            multisport
                ? { label: 'Legs', value: String(legs.length) }
                : {
                      label: 'Surface',
                      value: surfaces[route.surface] ?? 'Mixed',
                  },
        ],
    };
}

function mark(
    context: CanvasRenderingContext2D,
    x: number,
    y: number,
    size: number,
): void {
    const scale = size / 32;
    context.save();
    context.translate(x, y);
    context.scale(scale, scale);
    context.strokeStyle = paper;
    context.lineWidth = 2.7;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.stroke(
        new Path2D(
            'M8.2 25.2 V15.4 a5.1 5.1 0 0 1 5.1 -5.1 h3.4 a5.1 5.1 0 0 1 5.1 5.1 v2.3',
        ),
    );
    context.fillStyle = chartreuse;
    context.beginPath();
    context.arc(22.1, 23.5, 2.45, 0, Math.PI * 2);
    context.fill();
    context.restore();
}

/**
 * Splits a name into at most two lines that fit, and shortens the second
 * with an ellipsis when the name is longer still.
 */
function wrap(
    context: CanvasRenderingContext2D,
    text: string,
    maxWidth: number,
): string[] {
    const words = text.split(/\s+/);
    const lines: string[] = [];
    let line = '';

    for (const word of words) {
        const next = line === '' ? word : `${line} ${word}`;

        if (context.measureText(next).width <= maxWidth || line === '') {
            line = next;

            continue;
        }

        lines.push(line);
        line = word;
    }

    lines.push(line);

    if (lines.length <= 2) {
        return lines;
    }

    let last = lines.slice(1).join(' ');

    while (
        last.length > 1 &&
        context.measureText(`${last}…`).width > maxWidth
    ) {
        last = last.slice(0, -1);
    }

    return [lines[0] ?? '', `${last.trimEnd()}…`];
}

async function fonts(): Promise<void> {
    try {
        await Promise.all([
            document.fonts.load(`600 64px ${font}`),
            document.fonts.load(`400 22px ${font}`),
        ]);
    } catch {
        // The system font stands in.
    }
}

/**
 * Draws the picture in the browser, since only the browser has the map,
 * and gives it back as a JPEG. Null when the map could not draw.
 */
export async function drawShareImage(
    route: ShareImageRoute,
): Promise<Blob | null> {
    const points = route.lines.flatMap((line) => line.coordinates);
    const first = points[0];

    if (first === undefined || points.length < 2) {
        return null;
    }

    await fonts();
    const node = document.createElement('div');
    node.style.cssText = `position:fixed;left:0;top:0;width:${width}px;height:${height}px;visibility:hidden;pointer-events:none`;
    document.body.appendChild(node);
    const map = new maplibregl.Map({
        container: node,
        style: darkMapStyle,
        interactive: false,
        attributionControl: false,
        canvasContextAttributes: { preserveDrawingBuffer: true },
        fadeDuration: 0,
        pixelRatio: 1,
        center: first,
        zoom: 12,
    });

    try {
        await new Promise<void>((resolve) => map.once('load', () => resolve()));
        map.addSource('share', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: route.lines.map((line) => ({
                    type: 'Feature' as const,
                    properties: { color: line.color ?? paper },
                    geometry: {
                        type: 'LineString' as const,
                        coordinates: line.coordinates,
                    },
                })),
            },
        });
        map.addSource('share-start', {
            type: 'geojson',
            data: {
                type: 'Feature',
                properties: {},
                geometry: { type: 'Point', coordinates: first },
            },
        });
        map.addLayer({
            id: 'share-casing',
            type: 'line',
            source: 'share',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': ink, 'line-width': 11 },
        });
        map.addLayer({
            id: 'share-line',
            type: 'line',
            source: 'share',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': ['get', 'color'], 'line-width': 5 },
        });
        map.addLayer({
            id: 'share-start',
            type: 'circle',
            source: 'share-start',
            paint: {
                'circle-radius': 10,
                'circle-color': chartreuse,
                'circle-stroke-color': ink,
                'circle-stroke-width': 4,
            },
        });
        map.fitBounds(
            points.reduce(
                (box, point) => box.extend(point),
                new maplibregl.LngLatBounds(first, first),
            ),
            {
                padding: { top: 80, right: 80, bottom: 80, left: 720 },
                duration: 0,
                maxZoom: 15,
            },
        );
        await new Promise<void>((resolve) => {
            const done = window.setTimeout(resolve, 10_000);
            map.once('idle', () => {
                window.clearTimeout(done);
                resolve();
            });
            map.triggerRepaint();
        });

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');

        if (context === null) {
            return null;
        }

        context.fillStyle = ink;
        context.fillRect(0, 0, width, height);
        context.drawImage(map.getCanvas(), 0, 0, width, height);

        // The left side holds the words, so the map fades out under them.
        // It is clear again before the route begins.
        const shade = context.createLinearGradient(0, 0, 700, 0);
        shade.addColorStop(0, 'rgba(7, 31, 32, 0.97)');
        shade.addColorStop(0.8, 'rgba(7, 31, 32, 0.9)');
        shade.addColorStop(1, 'rgba(7, 31, 32, 0)');
        context.fillStyle = shade;
        context.fillRect(0, 0, width, height);

        mark(context, 48, 52, 44);
        context.fillStyle = paper;
        context.font = `600 30px ${font}`;
        context.textBaseline = 'middle';
        context.fillText('Traceway', 102, 75);

        context.textBaseline = 'alphabetic';
        context.font = `600 60px ${font}`;
        const title = wrap(context, route.name, 560);
        title.forEach((line, index) => {
            context.fillText(line, 48, 210 + index * 68);
        });

        context.fillStyle = chartreuse;
        context.font = `600 40px ${font}`;
        context.fillText(
            wrap(context, route.headline, 560)[0] ?? '',
            48,
            210 + title.length * 68 + 6,
        );

        const rowTop = height - 52 - route.facts.length * 44;
        route.facts.forEach((fact, index) => {
            const y = rowTop + index * 44;
            context.fillStyle = 'rgba(247, 245, 236, 0.16)';
            context.fillRect(48, y, 520, 1);
            context.font = `400 22px ${font}`;
            context.fillStyle = quiet;
            context.textAlign = 'left';
            context.fillText(fact.label, 48, y + 30);
            context.fillStyle = paper;
            context.textAlign = 'right';
            context.fillText(fact.value, 568, y + 30);
        });

        context.textAlign = 'right';
        context.font = `400 13px ${font}`;
        context.fillStyle = quiet;
        context.fillText(
            '© OpenStreetMap contributors · OpenFreeMap',
            width - 20,
            height - 16,
        );

        return await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.86),
        );
    } catch {
        return null;
    } finally {
        map.remove();
        node.remove();
    }
}

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    const token = match?.[1];

    return token === undefined ? '' : decodeURIComponent(token);
}

/**
 * Draws the picture of a saved route and keeps it on the server, so its
 * shared link shows it. Quiet when it fails: the link still works.
 */
export async function keepShareImage(
    routeId: string,
    route: ShareImageRoute,
): Promise<boolean> {
    const image = await drawShareImage(route);

    if (image === null) {
        return false;
    }

    const form = new FormData();
    form.append('image', image, 'route.jpg');

    try {
        const response = await fetch(`/routes/saved/${routeId}/image`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: form,
        });

        return response.ok;
    } catch {
        return false;
    }
}
