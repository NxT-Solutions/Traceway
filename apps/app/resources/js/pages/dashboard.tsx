import { Head, Link, usePage } from '@inertiajs/react';
import '../../css/planner.css';
import { Bike, Footprints, Mountain } from 'lucide-react';
import type { GeoJSONSource, MapMouseEvent } from 'maplibre-gl';
import * as maplibregl from 'maplibre-gl';
import {
    type ReactNode,
    type PointerEvent as ReactPointerEvent,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {
    type LegSheet,
    MultisportLegs,
    type Placing,
} from '@/components/multisport-legs';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { type ResolvedAppearance, useAppearance } from '@/hooks/use-appearance';
import {
    darkMapStyle,
    lightMapStyle,
    streetsMapStyle,
    themedMapStyle,
} from '@/lib/maplibre';
import { forgetSharedPlannerDraft, plannerDraftKey } from '@/lib/planner-draft';
import {
    isLandLeg,
    isCoordinate as isLegCoordinate,
    isPlan,
    joinedLine,
    type LandLeg,
    type Layout,
    legDeviceName,
    legPaint,
    type MultisportPlan,
    mostLandLegs,
    mostSwimPoints,
    multisportGpx,
    newLeg,
    presets,
    withIds,
} from '@/lib/multisport';
import {
    arrowIconSize,
    directionArrows,
    directionArrowsForLines,
    metresBetween,
    routeBadge,
} from '@/lib/route-arrows';
import { cn } from '@/lib/utils';
import 'maplibre-gl/dist/maplibre-gl.css';

type Coordinate = [number, number];
type SheetName =
    | 'sport'
    | 'length'
    | 'elevation'
    | 'surface'
    | 'shape'
    | 'roads'
    | 'map'
    | null;

type Preview = {
    distance_metres: number;
    ascent_metres: number;
    coordinates: Coordinate[];
    finish_coordinates?: Coordinate[];
    user_coordinates?: Coordinate[];
    user_metres?: number;
    finish_metres?: number;
};

type BuildPoint = {
    id: string;
    longitude: number;
    latitude: number;
    name: string;
};

const roadsOptions = [
    {
        value: 'quiet',
        label: 'Quiet first',
        copy: 'Score busy roads down hard.',
    },
    {
        value: 'any',
        label: 'Any road',
        copy: 'Let the shortest line win.',
    },
] as const;

const routePalette = [
    { line: '#16303a', hover: '#0c1418' },
    { line: '#c2410c', hover: '#9a3412' },
    { line: '#1d4ed8', hover: '#1e3a8a' },
] as const;

const routeQuiet = [
    { line: '#4d6672', hover: '#16303a' },
    { line: '#c45c32', hover: '#c2410c' },
    { line: '#3d68c9', hover: '#1d4ed8' },
] as const;

function buildMarkerElement(label: string): HTMLDivElement {
    const element = document.createElement('div');
    element.className = 'build-pin';
    element.textContent = label;

    return element;
}

function routePaint(index: number): { line: string; hover: string } {
    return routePalette[index % routePalette.length] ?? routePalette[0];
}

function Chevron() {
    return (
        <svg
            width="16"
            height="16"
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M6 3 L11 8 L6 13"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity="0.7"
            />
        </svg>
    );
}

type SportOption = {
    value: string;
    label: string;
    copy: string;
    group: 'Foot' | 'Bike';
};

const sports: SportOption[] = [
    {
        value: 'run',
        label: 'Run',
        copy: 'Paths and pavements. Busy roads drop out.',
        group: 'Foot',
    },
    {
        value: 'trail_run',
        label: 'Trail run',
        copy: 'Footpaths and dirt, with the climb left on.',
        group: 'Foot',
    },
    {
        value: 'walk',
        label: 'Walk',
        copy: 'The same loop, slower, for a shorter day.',
        group: 'Foot',
    },
    {
        value: 'hike',
        label: 'Hike',
        copy: 'Trails and hills, at a walking pace.',
        group: 'Foot',
    },
    {
        value: 'road',
        label: 'Road',
        copy: 'Quiet lanes and cycleways. Trunk roads lose.',
        group: 'Bike',
    },
    {
        value: 'mtb',
        label: 'Mountain bike',
        copy: 'Trail and rough track. The hills stay in.',
        group: 'Bike',
    },
    {
        value: 'gravel',
        label: 'Gravel',
        copy: 'Smooth dirt where the map knows it. Pavement still counts.',
        group: 'Bike',
    },
];

const elevations = [
    {
        value: 'neutral',
        label: 'Any',
        copy: 'Take the hills as they come.',
    },
    {
        value: 'minimize',
        label: 'Flat',
        copy: 'Avoid climbing where the roads allow.',
    },
    {
        value: 'maximize',
        label: 'Hilly',
        copy: 'Look for the climbs.',
    },
] as const;

const surfaces = [
    {
        value: 'mixed',
        label: 'Any',
        copy: 'Whatever the roads are made of.',
    },
    {
        value: 'paved',
        label: 'Paved',
        copy: 'Asphalt and concrete only.',
    },
    {
        value: 'dirt',
        label: 'Dirt',
        copy: 'Prefer unpaved where the map knows it.',
    },
] as const;

const mapStyles = [
    {
        id: 'auto',
        label: 'Match appearance',
        copy: 'Streets in light, the dark map in dark.',
        url: null,
    },
    {
        id: 'streets',
        label: 'Streets',
        copy: 'Roads, paths, and place names.',
        url: streetsMapStyle,
    },
    {
        id: 'light',
        label: 'Light',
        copy: 'A quieter map so the route stays easy to see.',
        url: lightMapStyle,
    },
    {
        id: 'dark',
        label: 'Dark',
        copy: 'For low light and evening planning.',
        url: darkMapStyle,
    },
] as const;

function mapStyleUrl(id: string, appearance: ResolvedAppearance): string {
    return (
        mapStyles.find((item) => item.id === id)?.url ??
        themedMapStyle(appearance)
    );
}

const shapes = [
    {
        value: 'loop',
        label: 'Loop',
        copy: 'Leave the pin and come back a different way.',
    },
    {
        value: 'out_and_back',
        label: 'Out and back',
        copy: 'Go out and return on the same roads.',
    },
] as const;

function syncRouteLines(
    map: maplibregl.Map,
    lines: Preview[],
    index: number,
    preview: Preview | null,
): void {
    const source = map.getSource('route');

    if (source === undefined || source.type !== 'geojson') {
        return;
    }

    const coordinates = preview?.coordinates ?? [];
    const solid =
        preview?.user_coordinates && preview.user_coordinates.length > 1
            ? preview.user_coordinates
            : coordinates;

    (source as GeoJSONSource).setData({
        ...emptyLine,
        geometry: {
            type: 'LineString',
            coordinates: solid,
        },
    });

    if (map.getLayer('route') !== undefined) {
        map.setPaintProperty('route', 'line-color', routePaint(index).line);
    }

    const finish = map.getSource('route-finish');

    if (finish !== undefined && finish.type === 'geojson') {
        (finish as GeoJSONSource).setData({
            ...emptyLine,
            geometry: {
                type: 'LineString',
                coordinates: preview?.finish_coordinates ?? [],
            },
        });
    }

    const faint = map.getSource('route-faint');

    if (faint !== undefined && faint.type === 'geojson') {
        (faint as GeoJSONSource).setData({
            type: 'FeatureCollection',
            features: lines.flatMap((line, lineIndex) =>
                lineIndex === index || line.coordinates.length < 2
                    ? []
                    : [
                          {
                              type: 'Feature' as const,
                              id: lineIndex,
                              properties: {
                                  index: lineIndex,
                                  color:
                                      routeQuiet[lineIndex % routeQuiet.length]
                                          ?.line ?? routeQuiet[0].line,
                                  hover:
                                      routeQuiet[lineIndex % routeQuiet.length]
                                          ?.hover ?? routeQuiet[0].hover,
                              },
                              geometry: {
                                  type: 'LineString' as const,
                                  coordinates: line.coordinates,
                              },
                          },
                      ],
            ),
        });
    }

    const arrows = map.getSource('route-arrows');

    if (arrows !== undefined && arrows.type === 'geojson') {
        (arrows as GeoJSONSource).setData(
            directionArrows(coordinates, map.getZoom()),
        );
    }
}

function addRouteLayers(map: maplibregl.Map, coordinates: Coordinate[]): void {
    const data = {
        ...emptyLine,
        geometry: { type: 'LineString' as const, coordinates },
    };

    if (map.getSource('route') === undefined) {
        map.addSource('route', { type: 'geojson', data });
    }

    if (map.getSource('build-draft') === undefined) {
        map.addSource('build-draft', { type: 'geojson', data: emptyLine });
    }

    if (map.getLayer('build-draft-casing') === undefined) {
        map.addLayer({
            id: 'build-draft-casing',
            type: 'line',
            source: 'build-draft',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': '#ffffff', 'line-width': 8 },
        });
    }

    if (map.getLayer('build-draft') === undefined) {
        map.addLayer({
            id: 'build-draft',
            type: 'line',
            source: 'build-draft',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': '#16303a', 'line-width': 3 },
        });
    }

    if (map.getLayer('route-casing') === undefined) {
        map.addLayer({
            id: 'route-casing',
            type: 'line',
            source: 'route',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': '#ffffff', 'line-width': 12 },
        });
    }

    if (map.getLayer('route') === undefined) {
        map.addLayer({
            id: 'route',
            type: 'line',
            source: 'route',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': '#101820', 'line-width': 6 },
        });
    }

    if (map.getSource('route-finish') === undefined) {
        map.addSource('route-finish', { type: 'geojson', data: emptyLine });
    }

    if (map.getLayer('route-finish') === undefined) {
        map.addLayer({
            id: 'route-finish',
            type: 'line',
            source: 'route-finish',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': '#16303a',
                'line-width': 4,
                'line-dasharray': [0.6, 1.4],
            },
        });
    }

    if (map.getSource('multisport') === undefined) {
        map.addSource('multisport', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: [] },
        });
    }

    if (map.getLayer('multisport-casing') === undefined) {
        map.addLayer({
            id: 'multisport-casing',
            type: 'line',
            source: 'multisport',
            filter: ['!=', ['get', 'discipline'], 'swim'],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': '#ffffff', 'line-width': 11 },
        });
    }

    if (map.getLayer('multisport') === undefined) {
        map.addLayer({
            id: 'multisport',
            type: 'line',
            source: 'multisport',
            filter: ['!=', ['get', 'discipline'], 'swim'],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': ['get', 'color'], 'line-width': 5 },
        });
    }

    if (map.getLayer('multisport-swim') === undefined) {
        map.addLayer({
            id: 'multisport-swim',
            type: 'line',
            source: 'multisport',
            filter: ['==', ['get', 'discipline'], 'swim'],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': ['get', 'color'],
                'line-width': 4,
                'line-dasharray': [1, 1.2],
            },
        });
    }

    if (map.getSource('route-faint') === undefined) {
        map.addSource('route-faint', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: [] },
        });
    }

    if (map.getLayer('route-faint-casing') === undefined) {
        map.addLayer(
            {
                id: 'route-faint-casing',
                type: 'line',
                source: 'route-faint',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#ffffff',
                    'line-width': 5.5,
                    'line-opacity': 0.8,
                },
            },
            'route-casing',
        );
    }

    if (map.getLayer('route-faint') === undefined) {
        map.addLayer(
            {
                id: 'route-faint',
                type: 'line',
                source: 'route-faint',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': [
                        'case',
                        ['boolean', ['feature-state', 'hover'], false],
                        ['get', 'hover'],
                        ['get', 'color'],
                    ],
                    'line-width': [
                        'case',
                        ['boolean', ['feature-state', 'hover'], false],
                        3.25,
                        2.5,
                    ],
                    'line-opacity': [
                        'case',
                        ['boolean', ['feature-state', 'hover'], false],
                        1,
                        0.82,
                    ],
                },
            },
            'route-casing',
        );
    }

    if (map.getLayer('route-faint-hit') === undefined) {
        map.addLayer(
            {
                id: 'route-faint-hit',
                type: 'line',
                source: 'route-faint',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#000000',
                    'line-width': 18,
                    'line-opacity': 0,
                },
            },
            'route-casing',
        );
    }

    if (map.getSource('route-arrows') === undefined) {
        map.addSource('route-arrows', {
            type: 'geojson',
            data: directionArrows(coordinates, map.getZoom()),
        });
    }

    if (!map.hasImage('route-badge')) {
        map.addImage('route-badge', routeBadge(), { pixelRatio: 2 });
    }

    if (map.getLayer('route-arrows') === undefined) {
        map.addLayer({
            id: 'route-arrows',
            type: 'symbol',
            source: 'route-arrows',
            layout: {
                'icon-image': 'route-badge',
                'icon-size': arrowIconSize(),
                'icon-rotate': ['get', 'bearing'],
                'icon-rotation-alignment': 'map',
                'icon-pitch-alignment': 'map',
                'icon-allow-overlap': true,
                'icon-ignore-placement': true,
            },
        });
    }
}

/**
 * Draws each leg of a multisport plan in its own colour. Before a plan is
 * drawn, the swim the athlete is drawing shows on its own.
 */
function syncMultisport(
    map: maplibregl.Map,
    plan: MultisportPlan | null,
    swim: Coordinate[] | null,
): void {
    const source = map.getSource('multisport');

    if (source === undefined || source.type !== 'geojson') {
        return;
    }

    const legs =
        plan?.legs ??
        (swim !== null && swim.length > 1
            ? [{ discipline: 'swim', coordinates: swim }]
            : []);

    (source as GeoJSONSource).setData({
        type: 'FeatureCollection',
        features: legs.flatMap((leg, index) =>
            leg.coordinates.length < 2
                ? []
                : [
                      {
                          type: 'Feature' as const,
                          id: index,
                          properties: {
                              discipline: leg.discipline,
                              color: legPaint(leg.discipline).line,
                          },
                          geometry: {
                              type: 'LineString' as const,
                              coordinates: leg.coordinates,
                          },
                      },
                  ],
        ),
    });
}

function paintArrowLines(map: maplibregl.Map, lines: Coordinate[][]): void {
    const arrows = map.getSource('route-arrows');

    if (arrows === undefined || arrows.type !== 'geojson') {
        return;
    }

    (arrows as GeoJSONSource).setData(
        directionArrowsForLines(lines, map.getZoom()),
    );
}

const emptyLine = {
    type: 'Feature' as const,
    geometry: { type: 'LineString' as const, coordinates: [] as Coordinate[] },
    properties: {},
};

async function labelPlace(
    longitude: number,
    latitude: number,
): Promise<string> {
    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}&zoom=14`,
            { headers: { Accept: 'application/json' } },
        );
        const body: unknown = await response.json();

        if (typeof body === 'object' && body !== null) {
            const record = body as {
                name?: string;
                address?: Record<string, string>;
            };
            const address = record.address ?? {};
            const name =
                address.village ||
                address.hamlet ||
                address.town ||
                address.suburb ||
                address.city ||
                record.name;

            if (typeof name === 'string' && name !== '') {
                return name;
            }
        }
    } catch {
        return 'Junction';
    }

    return 'Junction';
}

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    const token = match?.[1];

    return token === undefined ? '' : decodeURIComponent(token);
}

type RoadSnap = {
    longitude: number;
    latitude: number;
};

const snapRadius = 36;
const snapReach = 180;

type SnapSegment = {
    ax: number;
    ay: number;
    bx: number;
    by: number;
};

let roadSnapCache: {
    minX: number;
    minY: number;
    maxX: number;
    maxY: number;
    segments: SnapSegment[];
} | null = null;

function clearRoadSnapCache(): void {
    roadSnapCache = null;
}

const blockedRoadClass = new Set([
    'rail',
    'transit',
    'ferry',
    'aerialway',
    'motorway',
]);

function linesOf(geometry: unknown): Coordinate[][] {
    if (typeof geometry !== 'object' || geometry === null) {
        return [];
    }

    const record = geometry as { type?: string; coordinates?: unknown };

    if (record.type === 'LineString' && Array.isArray(record.coordinates)) {
        return [record.coordinates as Coordinate[]];
    }

    if (
        record.type === 'MultiLineString' &&
        Array.isArray(record.coordinates)
    ) {
        return record.coordinates as Coordinate[][];
    }

    return [];
}

function closestOnSegment(
    px: number,
    py: number,
    ax: number,
    ay: number,
    bx: number,
    by: number,
): { x: number; y: number; dist: number } {
    const dx = bx - ax;
    const dy = by - ay;
    const length = dx * dx + dy * dy;
    const t =
        length === 0
            ? 0
            : Math.max(
                  0,
                  Math.min(1, ((px - ax) * dx + (py - ay) * dy) / length),
              );
    const x = ax + t * dx;
    const y = ay + t * dy;

    return { x, y, dist: Math.hypot(x - px, y - py) };
}

function snapToRoad(
    map: maplibregl.Map,
    longitude: number,
    latitude: number,
): RoadSnap | null {
    if (!map.isStyleLoaded()) {
        return null;
    }

    const cursor = map.project([longitude, latitude]);
    const cache = roadSnapCache;
    const covered =
        cache !== null &&
        cursor.x > cache.minX + snapRadius &&
        cursor.x < cache.maxX - snapRadius &&
        cursor.y > cache.minY + snapRadius &&
        cursor.y < cache.maxY - snapRadius;
    let segments = cache?.segments ?? [];

    if (!covered) {
        let features: maplibregl.MapGeoJSONFeature[] = [];

        try {
            features = map.queryRenderedFeatures([
                [cursor.x - snapReach, cursor.y - snapReach],
                [cursor.x + snapReach, cursor.y + snapReach],
            ]);
        } catch {
            return null;
        }

        segments = [];

        for (const feature of features) {
            if (feature.layer.type !== 'line') {
                continue;
            }

            const layerId = feature.layer.id;

            if (
                layerId.includes('casing') ||
                layerId.includes('hatch') ||
                layerId.includes('arrow') ||
                layerId.includes('rail')
            ) {
                continue;
            }

            const onTransport =
                feature.sourceLayer === 'transportation' ||
                layerId.startsWith('road_') ||
                layerId.startsWith('tunnel_') ||
                layerId.startsWith('bridge_');

            if (!onTransport) {
                continue;
            }

            const kind = feature.properties?.class;

            if (typeof kind === 'string' && blockedRoadClass.has(kind)) {
                continue;
            }

            for (const line of linesOf(feature.geometry)) {
                for (let index = 1; index < line.length; index += 1) {
                    const start = line[index - 1];
                    const end = line[index];

                    if (start === undefined || end === undefined) {
                        continue;
                    }

                    const a = map.project(start);
                    const b = map.project(end);
                    segments.push({ ax: a.x, ay: a.y, bx: b.x, by: b.y });
                }
            }
        }

        roadSnapCache = {
            minX: cursor.x - snapReach,
            minY: cursor.y - snapReach,
            maxX: cursor.x + snapReach,
            maxY: cursor.y + snapReach,
            segments,
        };
    }

    let bestX = 0;
    let bestY = 0;
    let bestDist = snapRadius + 1;

    for (const segment of segments) {
        if (
            Math.max(segment.ax, segment.bx) < cursor.x - snapRadius ||
            Math.min(segment.ax, segment.bx) > cursor.x + snapRadius ||
            Math.max(segment.ay, segment.by) < cursor.y - snapRadius ||
            Math.min(segment.ay, segment.by) > cursor.y + snapRadius
        ) {
            continue;
        }

        const hit = closestOnSegment(
            cursor.x,
            cursor.y,
            segment.ax,
            segment.ay,
            segment.bx,
            segment.by,
        );

        if (hit.dist < bestDist) {
            bestDist = hit.dist;
            bestX = hit.x;
            bestY = hit.y;
        }
    }

    if (bestDist > snapRadius) {
        return null;
    }

    const snapped = map.unproject([bestX, bestY]);

    return { longitude: snapped.lng, latitude: snapped.lat };
}

function isCoordinate(value: unknown): value is Coordinate {
    return (
        Array.isArray(value) &&
        value.length === 2 &&
        typeof value[0] === 'number' &&
        typeof value[1] === 'number'
    );
}

function isPreview(value: unknown): value is Preview {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        typeof record.distance_metres === 'number' &&
        typeof record.ascent_metres === 'number' &&
        Array.isArray(record.coordinates) &&
        record.coordinates.every(isCoordinate)
    );
}

type SavedPlanner = {
    version: 1;
    latitude: number;
    longitude: number;
    distanceKm: number;
    sport: string;
    surface: string;
    elevation: string;
    shape: string;
    roads?: string;
    mapStyle: string;
    /**
     * Set once the map style can follow the appearance. Older saves stored
     * "streets" as the default, so they start on "auto" instead.
     */
    mapStyleVersion?: 2;
    name: string;
    via: Coordinate | null;
    routes: Preview[];
    routeIndex: number;
    multisport?: SavedMultisport;
};

type SavedMultisport = {
    legs: LandLeg[];
    layout: Layout;
    swim: Coordinate[] | null;
    plan: MultisportPlan | null;
};

function savedMultisport(saved: SavedPlanner | null): SavedMultisport | null {
    const value: unknown = saved?.multisport;

    if (typeof value !== 'object' || value === null) {
        return null;
    }

    const record = value as Record<string, unknown>;

    if (
        !Array.isArray(record.legs) ||
        record.legs.length === 0 ||
        !record.legs.every(isLandLeg) ||
        (record.layout !== 'loops' && record.layout !== 'chain') ||
        !(
            record.swim === null ||
            (Array.isArray(record.swim) && record.swim.every(isLegCoordinate))
        ) ||
        !(record.plan === null || isPlan(record.plan))
    ) {
        return null;
    }

    return record as SavedMultisport;
}

function isSaved(value: unknown): value is SavedPlanner {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        record.version === 1 &&
        typeof record.latitude === 'number' &&
        typeof record.longitude === 'number' &&
        typeof record.distanceKm === 'number' &&
        typeof record.sport === 'string' &&
        typeof record.surface === 'string' &&
        typeof record.elevation === 'string' &&
        typeof record.shape === 'string' &&
        typeof record.mapStyle === 'string' &&
        typeof record.name === 'string' &&
        (record.via === null || isCoordinate(record.via)) &&
        Array.isArray(record.routes) &&
        record.routes.every(isPreview) &&
        typeof record.routeIndex === 'number'
    );
}

function readSaved(storageKey: string): SavedPlanner | null {
    if (typeof window === 'undefined') {
        return null;
    }

    forgetSharedPlannerDraft();

    try {
        const raw = window.localStorage.getItem(storageKey);

        if (raw === null) {
            return null;
        }

        const parsed: unknown = JSON.parse(raw);

        return isSaved(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

function linesFrom(body: unknown): Preview[] | null {
    if (typeof body !== 'object' || body === null) {
        return null;
    }

    const record = body as Record<string, unknown>;

    if (Array.isArray(record.routes)) {
        const lines = record.routes.filter(isPreview);

        if (lines.length > 0 && lines.length === record.routes.length) {
            return lines;
        }
    }

    return isPreview(body) ? [body] : null;
}

function drawerProps(
    open: boolean,
    setOpen: (next: boolean) => void,
    panel: { current: HTMLElement | null },
    drag: {
        current: {
            y: number;
            height: number;
            moved: boolean;
            lastY: number;
            lastT: number;
            velocity: number;
        } | null;
    },
) {
    function limits(node: HTMLElement) {
        const summary = node.querySelector('.summary');
        const peek =
            summary instanceof HTMLElement
                ? Math.ceil(
                      summary.getBoundingClientRect().bottom -
                          node.getBoundingClientRect().top +
                          14,
                  )
                : 108;
        const full = Math.round(
            Math.min(window.innerHeight * 0.76, window.innerHeight - 64),
        );

        return { peek: Math.max(96, peek), full: Math.max(peek + 80, full) };
    }

    return {
        onPointerDown(event: {
            clientY: number;
            pointerId: number;
            timeStamp: number;
            currentTarget: HTMLElement;
        }) {
            const node = panel.current;

            if (!node || !window.matchMedia('(max-width: 1023px)').matches) {
                return;
            }

            event.currentTarget.setPointerCapture(event.pointerId);
            const height = node.getBoundingClientRect().height;
            drag.current = {
                y: event.clientY,
                height,
                moved: false,
                lastY: event.clientY,
                lastT: event.timeStamp,
                velocity: 0,
            };
        },
        onPointerMove(event: { clientY: number; timeStamp: number }) {
            const current = drag.current;
            const node = panel.current;

            if (!current || !node) {
                return;
            }

            const delta = event.clientY - current.y;

            if (Math.abs(delta) > 6) {
                current.moved = true;
            }

            current.velocity =
                (event.clientY - current.lastY) /
                Math.max(1, event.timeStamp - current.lastT);
            current.lastY = event.clientY;
            current.lastT = event.timeStamp;

            if (!current.moved) {
                return;
            }

            const { peek, full } = limits(node);
            const next = Math.min(full, Math.max(peek, current.height - delta));
            node.classList.add('dragging');
            node.style.height = `${next}px`;
        },
        onPointerUp() {
            const current = drag.current;
            const node = panel.current;
            drag.current = null;

            if (!current || !node) {
                return;
            }

            const height = node.getBoundingClientRect().height;
            const { peek, full } = limits(node);
            node.classList.remove('dragging');
            node.style.height = '';

            if (!current.moved) {
                node.classList.toggle('open', !open);
                setOpen(!open);

                return;
            }

            const pastHalfway = height > (peek + full) / 2;
            const flungOpen = current.velocity < -0.35;
            const flungShut = current.velocity > 0.35;
            const shouldOpen = flungShut ? false : flungOpen || pastHalfway;
            node.classList.toggle('open', shouldOpen);
            setOpen(shouldOpen);
        },
        onPointerCancel() {
            const node = panel.current;
            drag.current = null;
            node?.classList.remove('dragging');

            if (node) {
                node.style.height = '';
            }
        },
    };
}

function clampKm(value: number, fallback: number): number {
    if (!Number.isFinite(value)) {
        return fallback;
    }

    return Math.min(200, Math.max(5, Math.round(value)));
}

function bindLengthField(node: HTMLInputElement | null): void {
    if (node === null || node.dataset.twLength === '1') {
        return;
    }

    node.dataset.twLength = '1';
    // A full selection makes Firefox insert the next key a second time.
    node.select = () => {};

    let before = node.value;
    let lastInsert = { data: '', at: 0 };

    node.addEventListener('beforeinput', () => {
        before = node.value;
    });
    node.addEventListener('input', (event) => {
        if (!(event instanceof InputEvent)) {
            return;
        }

        if (event.inputType !== 'insertText' || event.data?.length !== 1) {
            return;
        }

        const typed = event.data;
        const value = node.value;
        const gained = value.length - before.length;
        const now = performance.now();
        const repeated =
            gained === 1 &&
            lastInsert.data === typed &&
            now - lastInsert.at < 16;
        lastInsert = { data: typed, at: now };

        if (gained <= 1 && !repeated) {
            return;
        }

        const caret = node.selectionStart ?? value.length;
        let next = value;
        let remove = repeated ? 1 : gained - 1;
        let at = caret;

        while (remove > 0 && at > 0 && next[at - 1] === typed) {
            next = next.slice(0, at - 1) + next.slice(at);
            at -= 1;
            remove -= 1;
        }

        const place = at;
        queueMicrotask(() => {
            node.value = next;
            node.setSelectionRange(place, place);
        });
    });
}

function LengthEditor({
    initialKm,
    onApply,
}: {
    initialKm: number;
    onApply: (kilometres: number) => void;
}) {
    const field = useRef<HTMLInputElement>(null);
    const committed = useRef(initialKm);

    function read(): number {
        const raw = field.current?.value.trim() ?? '';
        const parsed = Number(raw);

        if (raw === '' || !Number.isFinite(parsed)) {
            return committed.current;
        }

        return clampKm(parsed, committed.current);
    }

    function paint(kilometres: number) {
        committed.current = kilometres;

        if (field.current) {
            field.current.value = String(kilometres);
        }
    }

    function commit(): number {
        const next = read();
        paint(next);

        return next;
    }

    return (
        <>
            <div className="slider">
                <div className="val">
                    <label htmlFor="len-num">Preferred length</label>
                    <span className="numwrap">
                        <input
                            ref={(node) => {
                                field.current = node;
                                bindLengthField(node);
                            }}
                            id="len-num"
                            type="text"
                            inputMode="numeric"
                            name="traceway-length"
                            autoComplete="off"
                            autoCorrect="off"
                            spellCheck={false}
                            data-1p-ignore="true"
                            data-lpignore="true"
                            data-bwignore="true"
                            data-form-type="other"
                            aria-valuemin={5}
                            aria-valuemax={200}
                            defaultValue={String(initialKm)}
                            onKeyDown={(event) => {
                                if (event.key !== 'Enter') {
                                    return;
                                }

                                event.preventDefault();
                                onApply(commit());
                            }}
                        />
                        <b>km</b>
                    </span>
                </div>
            </div>
            <p className="small">
                Whole kilometres from 5 to 200. Loops land within a few
                kilometres of this. The three closest are kept.
            </p>
            <div className="foot">
                <button
                    type="button"
                    className="textbtn"
                    onClick={() => paint(40)}
                >
                    Reset
                </button>
                <button
                    type="button"
                    className="outline sm"
                    onClick={() => onApply(commit())}
                >
                    Apply
                </button>
            </div>
        </>
    );
}

function describe(shapeValue: string, viaLabel: string | null): string {
    const through = viaLabel === null ? '' : `Through ${viaLabel}. `;
    const way =
        shapeValue === 'out_and_back'
            ? 'Out and back follows the same roads there and home.'
            : 'The loop comes back to the pin on different roads.';

    return `${through}Arrows point the way you travel. ${way}`;
}

const choiceSheet =
    'tw-pop top-auto block h-fit max-h-[70vh] gap-3 overflow-y-auto bg-transparent p-0 pb-1 text-foreground shadow-none';

const sheetHeading = 'gap-0 p-0';

function gpx(name: string, coordinates: Coordinate[]): string {
    const points = coordinates
        .map(
            ([longitude, latitude]) =>
                `<trkpt lat="${latitude}" lon="${longitude}"></trkpt>`,
        )
        .join('');

    return `<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" creator="Traceway"><trk><name>${name}</name><trkseg>${points}</trkseg></trk></gpx>`;
}

function sportIcon(value: string) {
    if (value === 'mtb') {
        return Mountain;
    }

    if (value === 'road' || value === 'gravel') {
        return Bike;
    }

    return Footprints;
}

export default function Dashboard() {
    const { resolvedAppearance } = useAppearance();
    const mapNode = useRef<HTMLDivElement>(null);
    const mapRef = useRef<maplibregl.Map | null>(null);
    const appliedStyleRef = useRef<string | null>(null);
    const framePaddingRef = useRef<() => number | maplibregl.PaddingOptions>(
        () => 80,
    );
    const fitRouteRef = useRef<(duration: number) => void>(() => {});
    const markerRef = useRef<maplibregl.Marker | null>(null);
    const viaMarkerRef = useRef<maplibregl.Marker | null>(null);
    const buildMarkersRef = useRef<maplibregl.Marker[]>([]);
    const previewRef = useRef<Preview | null>(null);
    const placingViaRef = useRef(false);
    const onMapClickRef = useRef<(lng: number, lat: number) => void>(() => {});
    const selectLoopRef = useRef<(index: number) => void>(() => {});
    const modeRef = useRef<'generate' | 'build' | 'multisport'>('generate');
    const snapMarkerRef = useRef<maplibregl.Marker | null>(null);
    const hoverSnapRef = useRef<{
        queryLng: number;
        queryLat: number;
        point: RoadSnap | null;
    } | null>(null);
    const snapFrameRef = useRef(0);
    const snapCursorRef = useRef<{ lng: number; lat: number } | null>(null);
    const moveSnapRef = useRef<(lng: number, lat: number) => void>(() => {});
    const hideSnapRef = useRef<() => void>(() => {});
    const routesRef = useRef<Preview[]>([]);
    const routeIndexRef = useRef(0);
    const { auth } = usePage<{ auth: { user: { id: number; name: string } } }>()
        .props;
    const storageKey = plannerDraftKey(auth.user.id);
    const saved = useRef(readSaved(storageKey)).current;
    const dirty = useRef(saved !== null);
    const [latitude, setLatitude] = useState(saved?.latitude ?? 51.05);
    const [longitude, setLongitude] = useState(saved?.longitude ?? 3.72);
    const [distanceKm, setDistanceKm] = useState(saved?.distanceKm ?? 20);
    const [sport, setSport] = useState(saved?.sport ?? 'road');
    const [surface, setSurface] = useState(saved?.surface ?? 'paved');
    const [elevation, setElevation] = useState(saved?.elevation ?? 'neutral');
    const [shape, setShape] = useState(saved?.shape ?? 'loop');
    const [roads, setRoads] = useState<'quiet' | 'any'>(
        saved?.roads === 'any' ? 'any' : 'quiet',
    );
    const [mode, setMode] = useState<'generate' | 'build' | 'multisport'>(
        'generate',
    );
    const savedPlan = useRef(savedMultisport(saved)).current;
    const [legs, setLegs] = useState<LandLeg[]>(
        () => savedPlan?.legs ?? withIds(presets[0].legs),
    );
    const [layout, setLayout] = useState<Layout>(savedPlan?.layout ?? 'loops');
    const [swim, setSwim] = useState<Coordinate[] | null>(
        savedPlan?.swim ?? null,
    );
    const [activeLeg, setActiveLeg] = useState(0);
    const [placing, setPlacing] = useState<Placing>(null);
    const [plan, setPlan] = useState<MultisportPlan | null>(
        savedPlan?.plan ?? null,
    );
    const multisportMarkersRef = useRef<maplibregl.Marker[]>([]);
    const multisportRef = useRef<{
        plan: MultisportPlan | null;
        swim: Coordinate[] | null;
    }>({ plan: null, swim: null });
    const arrowLinesRef = useRef<Coordinate[][]>([]);
    const [routeSaved, setRouteSaved] = useState(false);
    const [saving, setSaving] = useState(false);
    modeRef.current = mode;
    const [buildPoints, setBuildPoints] = useState<BuildPoint[]>([]);
    const pointsListRef = useRef<HTMLDivElement>(null);
    const pointShiftRef = useRef<Map<string, number> | null>(null);
    const [followRoads, setFollowRoads] = useState(true);
    const [finishLoop, setFinishLoop] = useState(true);
    const [viaName, setViaName] = useState<string | null>(null);
    const [legMetres, setLegMetres] = useState<number[]>([]);
    const nameInputRef = useRef<HTMLInputElement>(null);
    const nameBeforeEdit = useRef('Morning loop');
    const geolocateRef = useRef<maplibregl.GeolocateControl | null>(null);
    const locateRef = useRef<() => void>(() => {});
    const [mapStyle, setMapStyle] = useState(
        saved?.mapStyleVersion === 2 ? saved.mapStyle : 'auto',
    );
    const [name, setName] = useState(saved?.name ?? 'Morning loop');
    const [renaming, setRenaming] = useState(false);
    const [sheet, setSheet] = useState<SheetName>(null);
    const [panelOpen, setPanelOpen] = useState(false);
    const panelRef = useRef<HTMLElement>(null);
    const drawerDrag = useRef<{
        y: number;
        height: number;
        moved: boolean;
        lastY: number;
        lastT: number;
        velocity: number;
    } | null>(null);

    useEffect(() => {
        if (
            sheet === null ||
            !window.matchMedia('(max-width: 1023px)').matches
        ) {
            return;
        }

        let frame = 0;
        let detach = () => {};

        const attach = () => {
            const found = document.querySelector('[data-slot="sheet-content"]');

            if (!(found instanceof HTMLElement)) {
                frame = window.requestAnimationFrame(attach);

                return;
            }

            let drag: {
                y: number;
                lastY: number;
                lastT: number;
                velocity: number;
                active: boolean;
            } | null = null;

            const down = (event: PointerEvent) => {
                if (event.button !== 0) {
                    return;
                }

                const target = event.target;

                if (!(target instanceof Element)) {
                    return;
                }

                if (target.closest('input, textarea, select, button, a')) {
                    return;
                }

                const fromGrab =
                    event.clientY - found.getBoundingClientRect().top < 28;
                drag = {
                    y: event.clientY,
                    lastY: event.clientY,
                    lastT: event.timeStamp,
                    velocity: 0,
                    active: fromGrab,
                };

                if (fromGrab) {
                    found.setPointerCapture(event.pointerId);
                }
            };
            const move = (event: PointerEvent) => {
                if (!drag) {
                    return;
                }

                const delta = event.clientY - drag.y;
                drag.velocity =
                    (event.clientY - drag.lastY) /
                    Math.max(1, event.timeStamp - drag.lastT);
                drag.lastY = event.clientY;
                drag.lastT = event.timeStamp;

                if (!drag.active) {
                    if (delta > 8 && found.scrollTop <= 0) {
                        drag.active = true;
                        found.setPointerCapture(event.pointerId);
                    } else {
                        return;
                    }
                }

                found.style.transition = 'none';
                found.style.transform = `translateY(${Math.max(0, delta)}px)`;
                fitRouteRef.current(0);
            };
            const up = () => {
                if (!drag?.active) {
                    drag = null;

                    return;
                }

                const match = /translateY\(([-\d.]+)px\)/.exec(
                    found.style.transform,
                );
                const delta = match ? Number(match[1]) : 0;
                const reduce = window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches;
                found.style.transition = reduce
                    ? 'none'
                    : 'transform 180ms ease';

                if (delta > 96 || drag.velocity > 0.65) {
                    found.style.transform = 'translateY(110%)';
                    window.setTimeout(() => setSheet(null), reduce ? 0 : 170);
                } else {
                    found.style.transform = 'translateY(0px)';
                }

                const started = performance.now();
                const follow = () => {
                    fitRouteRef.current(0);

                    if (performance.now() - started < 200) {
                        window.requestAnimationFrame(follow);
                    }
                };
                window.requestAnimationFrame(follow);

                drag = null;
            };

            found.addEventListener('pointerdown', down);
            found.addEventListener('pointermove', move);
            found.addEventListener('pointerup', up);
            found.addEventListener('pointercancel', up);
            detach = () => {
                found.removeEventListener('pointerdown', down);
                found.removeEventListener('pointermove', move);
                found.removeEventListener('pointerup', up);
                found.removeEventListener('pointercancel', up);
                found.style.transform = '';
                found.style.transition = '';
            };
        };

        attach();

        return () => {
            window.cancelAnimationFrame(frame);
            detach();
        };
    }, [sheet]);
    const [sportQuery, setSportQuery] = useState('');
    const [routes, setRoutes] = useState<Preview[]>(saved?.routes ?? []);
    const [routeIndex, setRouteIndex] = useState(saved?.routeIndex ?? 0);
    const [via, setVia] = useState<Coordinate | null>(saved?.via ?? null);
    const [placingVia, setPlacingVia] = useState(false);
    const [message, setMessage] = useState(() => {
        const line = saved?.routes[saved.routeIndex] ?? saved?.routes[0];

        return line === undefined
            ? 'Tap the map to move the start.'
            : describe(saved?.shape ?? 'loop', null);
    });
    const [busy, setBusy] = useState(false);
    const settledStatus = useRef(message);

    if (!busy && message !== 'Drawing the loop…') {
        settledStatus.current = message;
    }

    const rider = auth.user.name;
    const riderInitials = rider
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase();
    const preview = routes[routeIndex] ?? routes[0] ?? null;
    const multisport = mode === 'multisport';
    const planLine: Preview | null =
        plan === null
            ? null
            : {
                  distance_metres: plan.distance_metres,
                  ascent_metres: plan.ascent_metres,
                  coordinates: joinedLine(plan),
              };
    const shown = multisport ? planLine : preview;
    multisportRef.current = { plan: multisport ? plan : null, swim };
    arrowLinesRef.current = multisport
        ? (plan?.legs ?? [])
              .filter((leg) => leg.discipline !== 'swim')
              .map((leg) => leg.coordinates)
        : [preview?.coordinates ?? []];
    const peekLabel = [
        panelOpen ? 'open' : 'closed',
        shown ? (shown.distance_metres / 1000).toFixed(1) : String(distanceKm),
        multisport
            ? `${legs.length + (swim === null ? 0 : 1)} legs`
            : routes.length > 0
              ? `${routeIndex + 1}/${routes.length}`
              : shape,
        surface,
    ].join(' ');

    previewRef.current = multisport ? null : preview;

    useEffect(() => {
        const node = panelRef.current;

        if (!node) {
            return;
        }

        node.dataset.summary = peekLabel;

        const fit = () => {
            if (!window.matchMedia('(max-width: 1023px)').matches) {
                node.style.removeProperty('--peek');

                return;
            }

            const summary = node.querySelector('.summary');

            if (!(summary instanceof HTMLElement)) {
                return;
            }

            const peek = Math.ceil(
                summary.getBoundingClientRect().bottom -
                    node.getBoundingClientRect().top +
                    14,
            );
            node.style.setProperty('--peek', `${Math.max(96, peek)}px`);
        };

        fit();
        window.addEventListener('resize', fit);

        return () => window.removeEventListener('resize', fit);
    }, [peekLabel]);
    routesRef.current = routes;
    routeIndexRef.current = routeIndex;
    placingViaRef.current = placingVia;

    const sportLabel =
        sports.find((item) => item.value === sport)?.label ?? 'Road';
    const shapeLabel =
        shapes.find((item) => item.value === shape)?.label ?? 'Loop';
    const surfaceLabel =
        surfaces.find((item) => item.value === surface)?.label ?? 'Paved';
    const elevationLabel =
        elevations.find((item) => item.value === elevation)?.label ?? 'Any';
    const roadsLabel =
        roadsOptions.find((item) => item.value === roads)?.label ??
        'Quiet first';
    const editing = multisport ? (legs[activeLeg] ?? legs[0]) : undefined;
    const sheetSport = editing?.sport ?? sport;
    const sheetSurface = editing?.surface ?? surface;
    const sheetElevation = editing?.elevation ?? elevation;
    const sheetRoads = editing?.roads ?? roads;
    const sheetKm = editing?.distanceKm ?? distanceKm;
    const SportIcon = sportIcon(multisport ? (legs[0]?.sport ?? sport) : sport);
    const visibleSports = useMemo(
        () =>
            sports.filter((item) =>
                item.label
                    .toLowerCase()
                    .includes(sportQuery.trim().toLowerCase()),
            ),
        [sportQuery],
    );

    const mapStartRef = useRef({
        longitude,
        latitude,
        mapStyle,
        resolvedAppearance,
    });
    mapStartRef.current = { longitude, latitude, mapStyle, resolvedAppearance };

    useEffect(() => {
        const node = mapNode.current;
        const start = mapStartRef.current;

        if (node === null || mapRef.current !== null) {
            return;
        }

        const startingStyle = mapStyleUrl(
            start.mapStyle,
            start.resolvedAppearance,
        );
        appliedStyleRef.current = startingStyle;
        const map = new maplibregl.Map({
            container: node,
            style: startingStyle,
            center: [start.longitude, start.latitude],
            zoom: 11,
            attributionControl: false,
        });

        const geolocate = new maplibregl.GeolocateControl({
            positionOptions: { enableHighAccuracy: true },
            trackUserLocation: false,
            showUserLocation: true,
            showAccuracyCircle: true,
            zoomToUserAccuracy: false,
        });

        geolocate.on(
            'geolocate',
            (event: { coords: GeolocationCoordinates }) => {
                dirty.current = true;
                setLatitude(event.coords.latitude);
                setLongitude(event.coords.longitude);
                setRoutes([]);
                setRouteIndex(0);
                setPlan(null);
                setMessage(
                    'Start is your location. Draw a route when you are ready.',
                );
                const reduced = window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches;
                map.easeTo({
                    center: [event.coords.longitude, event.coords.latitude],
                    zoom: Math.max(map.getZoom(), 15),
                    padding: framePaddingRef.current(),
                    duration: reduced ? 0 : 180,
                });
            },
        );
        geolocate.on('error', () => {
            setMessage(
                'Location is unavailable. Tap the map to set the start.',
            );
        });

        geolocateRef.current = geolocate;
        map.addControl(geolocate, 'top-right');

        map.on('error', (event) => {
            const error = event.error;
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'The map tiles failed to load.',
            );
        });

        const paintLoadedStyle = (): void => {
            addRouteLayers(map, previewRef.current?.coordinates ?? []);
            syncRouteLines(
                map,
                modeRef.current === 'multisport' ? [] : routesRef.current,
                routeIndexRef.current,
                previewRef.current,
            );
            syncMultisport(
                map,
                multisportRef.current.plan,
                multisportRef.current.swim,
            );
            paintArrowLines(map, arrowLinesRef.current);
        };

        map.on('style.load', paintLoadedStyle);

        if (map.isStyleLoaded()) {
            paintLoadedStyle();
        }

        map.on('click', (event: MapMouseEvent) => {
            if (
                modeRef.current === 'generate' &&
                map.getLayer('route-faint-hit') !== undefined &&
                map.queryRenderedFeatures(event.point, { layers: ['route'] })
                    .length === 0
            ) {
                const hits = map.queryRenderedFeatures(event.point, {
                    layers: ['route-faint-hit'],
                });
                const raw = hits[0]?.properties?.index;
                const index = typeof raw === 'number' ? raw : Number(raw);

                if (Number.isInteger(index) && index >= 0) {
                    selectLoopRef.current(index);

                    return;
                }
            }

            onMapClickRef.current(event.lngLat.lng, event.lngLat.lat);
        });
        map.on('mousemove', (event: MapMouseEvent) => {
            if (modeRef.current === 'build') {
                map.getCanvas().style.cursor = 'crosshair';
                map.getCanvasContainer().style.cursor = 'crosshair';
                moveSnapRef.current(event.lngLat.lng, event.lngLat.lat);

                return;
            }

            map.getCanvasContainer().style.cursor = '';
            hideSnapRef.current();

            if (
                modeRef.current !== 'generate' ||
                map.getLayer('route-faint') === undefined
            ) {
                map.getCanvas().style.cursor = '';

                return;
            }

            const onSelected = map.queryRenderedFeatures(event.point, {
                layers: ['route'],
            });
            const hits =
                onSelected.length === 0
                    ? map.queryRenderedFeatures(event.point, {
                          layers: ['route-faint-hit'],
                      })
                    : [];
            const raw = hits[0]?.properties?.index;
            const index = typeof raw === 'number' ? raw : Number(raw);
            const hovering = Number.isInteger(index) && index >= 0;
            map.getCanvas().style.cursor = hovering ? 'pointer' : '';

            for (const feature of map.querySourceFeatures('route-faint')) {
                if (feature.id === undefined) {
                    continue;
                }

                map.setFeatureState(
                    { source: 'route-faint', id: feature.id },
                    { hover: hovering && feature.id === index },
                );
            }
        });
        const dropRoadSnap = (): void => {
            clearRoadSnapCache();
        };
        map.on('move', dropRoadSnap);
        map.on('resize', dropRoadSnap);
        map.on('mouseout', () => {
            if (modeRef.current === 'build') {
                hideSnapRef.current();
            }
        });
        map.on('dragstart', () => {
            if (modeRef.current === 'build') {
                map.getCanvas().style.cursor = 'crosshair';
                hideSnapRef.current();
            }
        });
        map.on('zoomend', () => {
            const arrows = map.getSource('route-arrows');

            if (arrows === undefined || arrows.type !== 'geojson') {
                return;
            }

            (arrows as GeoJSONSource).setData(
                directionArrowsForLines(arrowLinesRef.current, map.getZoom()),
            );
        });

        mapRef.current = map;
        markerRef.current = new maplibregl.Marker({
            color: '#16303a',
            className: 'tw-start-pin',
        })
            .setLngLat([start.longitude, start.latitude])
            .addTo(map);

        // MapLibre sets position:relative on its root, which drops the
        // absolute sizing. Resize once the box has a real height.
        requestAnimationFrame(() => map.resize());
        const applyArrowSize = () => {
            if (map.getLayer('route-arrows') !== undefined) {
                map.setLayoutProperty(
                    'route-arrows',
                    'icon-size',
                    arrowIconSize(),
                );
            }
        };
        const observer = new ResizeObserver(() => {
            map.resize();
            applyArrowSize();
        });
        observer.observe(node);
        window.addEventListener('resize', applyArrowSize);
        map.once('load', () => {
            const line = previewRef.current;

            if (line !== null && line.coordinates.length > 1) {
                fitRouteRef.current(0);

                return;
            }

            if (saved === null) {
                locateRef.current();
            }
        });

        return () => {
            hideSnapRef.current();
            observer.disconnect();
            window.removeEventListener('resize', applyArrowSize);
            map.remove();
            mapRef.current = null;
            markerRef.current = null;
            viaMarkerRef.current = null;
            snapMarkerRef.current = null;
        };
    }, [saved]);

    useEffect(() => {
        markerRef.current?.setLngLat([longitude, latitude]);
    }, [latitude, longitude]);

    useEffect(() => {
        if (renaming) {
            nameInputRef.current?.focus();
        }
    }, [renaming]);

    useLayoutEffect(() => {
        const before = pointShiftRef.current;
        const list = pointsListRef.current;
        const ids = new Set(buildPoints.map((point) => point.id));
        pointShiftRef.current = null;

        if (before === null || list === null) {
            return;
        }

        const nodes = [...list.querySelectorAll<HTMLElement>('[data-point]')];
        const shifts: Array<{ node: HTMLElement; dy: number }> = [];

        for (const node of nodes) {
            const id = node.dataset.point;

            if (id === undefined || !ids.has(id)) {
                continue;
            }

            const top = before.get(id);

            if (top === undefined) {
                continue;
            }

            node.style.transition = 'none';
            node.style.transform = '';
            const dy = top - node.getBoundingClientRect().top;

            if (Math.abs(dy) < 1) {
                node.style.transition = '';

                continue;
            }

            node.style.transform = `translateY(${dy}px)`;
            shifts.push({ node, dy });
        }

        if (shifts.length === 0) {
            return;
        }

        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        requestAnimationFrame(() => {
            for (const { node } of shifts) {
                node.style.transition = reduced
                    ? 'none'
                    : 'transform 180ms var(--ease)';
                node.style.transform = '';
            }
        });
    }, [buildPoints]);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        const cursor =
            mode === 'build' || (mode === 'multisport' && placing !== null)
                ? 'crosshair'
                : '';
        map.getCanvas().style.cursor = cursor;
        map.getCanvasContainer().style.cursor = cursor;

        if (mode !== 'build') {
            hideSnapRef.current();
        }
    }, [mode, placing]);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        const paint = (): void => {
            syncRouteLines(
                map,
                multisport ? [] : routes,
                routeIndex,
                multisport ? null : preview,
            );
            syncMultisport(
                map,
                multisport ? plan : null,
                multisport ? swim : null,
            );
            paintArrowLines(map, arrowLinesRef.current);
        };

        if (map.getSource('route-faint') !== undefined) {
            paint();

            return;
        }

        map.once('idle', paint);
    }, [preview, routes, routeIndex, multisport, plan, swim]);

    useEffect(() => {
        const map = mapRef.current;

        for (const marker of multisportMarkersRef.current) {
            marker.remove();
        }

        multisportMarkersRef.current = [];

        if (map === null || !multisport) {
            return;
        }

        const offset = swim === null ? 1 : 2;

        if (layout === 'chain') {
            legs.forEach((leg, index) => {
                if (leg.finish === null) {
                    return;
                }

                const element = document.createElement('div');
                element.className = 'finish-pin';
                element.textContent = String(index + offset);
                multisportMarkersRef.current.push(
                    new maplibregl.Marker({ element, anchor: 'center' })
                        .setLngLat(leg.finish)
                        .addTo(map),
                );
            });
        }

        for (const point of swim ?? []) {
            const element = document.createElement('div');
            element.className = 'swim-pin';
            multisportMarkersRef.current.push(
                new maplibregl.Marker({ element, anchor: 'center' })
                    .setLngLat(point)
                    .addTo(map),
            );
        }
    }, [multisport, layout, legs, swim]);

    useEffect(() => {
        if (!dirty.current) {
            return;
        }

        const payload: SavedPlanner = {
            version: 1,
            latitude,
            longitude,
            distanceKm,
            sport,
            surface,
            elevation,
            shape,
            roads,
            mapStyle,
            mapStyleVersion: 2,
            name,
            via,
            routes,
            routeIndex,
            multisport: { legs, layout, swim, plan },
        };
        window.localStorage.setItem(storageKey, JSON.stringify(payload));
    }, [
        storageKey,
        latitude,
        longitude,
        distanceKm,
        sport,
        surface,
        elevation,
        shape,
        roads,
        mapStyle,
        name,
        via,
        routes,
        routeIndex,
        legs,
        layout,
        swim,
        plan,
    ]);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        if (via === null) {
            viaMarkerRef.current?.remove();
            viaMarkerRef.current = null;

            return;
        }

        if (viaMarkerRef.current === null) {
            viaMarkerRef.current = new maplibregl.Marker({ color: '#c6e84a' })
                .setLngLat(via)
                .addTo(map);

            return;
        }

        viaMarkerRef.current.setLngLat(via);
    }, [via]);

    useEffect(() => {
        const map = mapRef.current;

        for (const marker of buildMarkersRef.current) {
            marker.remove();
        }

        buildMarkersRef.current = [];

        if (map === null) {
            return;
        }

        const draft: Coordinate[] =
            mode === 'build'
                ? [
                      [longitude, latitude],
                      ...buildPoints.map(
                          (point) =>
                              [point.longitude, point.latitude] as Coordinate,
                      ),
                  ]
                : [];
        const showDraft = draft.length > 1 && preview === null;

        const syncDraft = () => {
            const source = map.getSource('build-draft');

            if (source === undefined || source.type !== 'geojson') {
                return;
            }

            (source as GeoJSONSource).setData({
                ...emptyLine,
                geometry: {
                    type: 'LineString',
                    coordinates: showDraft ? draft : [],
                },
            });
        };

        if (map.isStyleLoaded()) {
            syncDraft();
        } else {
            map.once('idle', syncDraft);
        }

        if (mode !== 'build') {
            return;
        }

        buildPoints.forEach((point, index) => {
            const marker = new maplibregl.Marker({
                element: buildMarkerElement(String(index + 1)),
                anchor: 'center',
            })
                .setLngLat([point.longitude, point.latitude])
                .addTo(map);
            buildMarkersRef.current.push(marker);
        });
    }, [buildPoints, mode, longitude, latitude, preview]);

    function paintArrows(lines: Coordinate[][]): void {
        const map = mapRef.current;
        const arrows = map?.getSource('route-arrows');

        if (map === null || arrows === undefined || arrows.type !== 'geojson') {
            return;
        }

        (arrows as GeoJSONSource).setData(
            directionArrowsForLines(lines, map.getZoom()),
        );
    }

    function framePadding(): number | maplibregl.PaddingOptions {
        const map = mapRef.current;

        if (map === null || !window.matchMedia('(max-width: 1023px)').matches) {
            return 80;
        }

        const canvas = map.getContainer().getBoundingClientRect();
        let cover = 0;
        const panel = panelRef.current;

        if (panel !== null) {
            const box = panel.getBoundingClientRect();
            cover = Math.max(0, canvas.bottom - box.top);
        }

        const sheet = document.querySelector('[data-slot="sheet-content"]');

        if (sheet instanceof HTMLElement) {
            const box = sheet.getBoundingClientRect();

            if (box.width > 0 && box.height > 0) {
                cover = Math.max(0, cover, canvas.bottom - box.top);
            }
        }

        const visible = canvas.height - cover;
        const edge = visible < 200 ? 12 : 28;
        const bottom = Math.min(
            Math.max(12, canvas.height - edge - 48),
            cover + 44,
        );

        return {
            top: edge,
            right: visible < 200 ? 16 : 72,
            bottom,
            left: edge,
        };
    }

    framePaddingRef.current = framePadding;

    function showPoint(lng: number, lat: number): void {
        const map = mapRef.current;

        if (map === null || !map.isStyleLoaded()) {
            return;
        }

        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        map.easeTo({
            center: [lng, lat],
            zoom: Math.max(map.getZoom(), 15),
            padding: framePadding(),
            duration: reduced ? 0 : 180,
        });
    }

    function locateMe(): void {
        const control = geolocateRef.current;

        if (control === null) {
            return;
        }

        control.options.fitBoundsOptions = {
            padding: framePadding(),
            maxZoom: 16,
        };
        void control.trigger();
    }

    locateRef.current = locateMe;

    function fitLine(
        line: Preview,
        duration = 180,
        arrowLines: Coordinate[][] = [line.coordinates],
    ): void {
        const map = mapRef.current;
        const first = line.coordinates[0];

        if (
            first === undefined ||
            map === null ||
            !map.isStyleLoaded() ||
            map.getContainer().clientHeight < 50
        ) {
            return;
        }

        if (duration > 0) {
            map.once('moveend', () => {
                paintArrows(arrowLines);
            });
        }

        map.fitBounds(
            line.coordinates.reduce(
                (bounds, coordinate) => bounds.extend(coordinate),
                new maplibregl.LngLatBounds(first, first),
            ),
            { padding: framePadding(), duration, maxZoom: 16 },
        );

        if (duration === 0) {
            paintArrows(arrowLines);
        }
    }

    fitRouteRef.current = (duration: number) => {
        const line =
            modeRef.current === 'multisport' ? planLine : previewRef.current;

        if (line !== null) {
            fitLine(line, duration, arrowLinesRef.current);
        }
    };

    useEffect(() => {
        const panel = panelRef.current;

        if (panel === null) {
            return;
        }

        const observer = new ResizeObserver(() => {
            if (!window.matchMedia('(max-width: 1023px)').matches) {
                return;
            }

            fitRouteRef.current(0);
        });
        observer.observe(panel);

        return () => observer.disconnect();
    }, []);

    async function generate(next?: {
        sport?: string;
        surface?: string;
        elevation?: string;
        distanceKm?: number;
        shape?: string;
        via?: Coordinate | null;
        roads?: 'quiet' | 'any';
    }): Promise<void> {
        setRouteSaved(false);
        const chosenSport = next?.sport ?? sport;
        const chosenSurface = next?.surface ?? surface;
        const chosenElevation = next?.elevation ?? elevation;
        const chosenDistance = next?.distanceKm ?? distanceKm;
        const chosenShape = next?.shape ?? shape;
        const chosenVia = next?.via === undefined ? via : next.via;
        const chosenRoads = next?.roads ?? roads;

        dirty.current = true;
        setBusy(true);
        setMessage('Drawing the loop…');

        const response = await fetch('/routes/preview', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                sport: chosenSport,
                surface: chosenSurface,
                elevation: chosenElevation,
                shape: chosenShape,
                roads: chosenRoads,
                distance_metres: Math.round(chosenDistance * 1000),
                latitude,
                longitude,
                ...(chosenVia === null
                    ? {}
                    : {
                          via_latitude: chosenVia[1],
                          via_longitude: chosenVia[0],
                      }),
            }),
        });

        const body: unknown = await response.json();
        setBusy(false);

        const lines = linesFrom(body);

        if (!response.ok || lines === null) {
            const record =
                typeof body === 'object' && body !== null
                    ? (body as Record<string, unknown>)
                    : {};
            setMessage(
                typeof record.message === 'string'
                    ? record.message
                    : 'No loop came back from that pin.',
            );

            return;
        }

        setRoutes(lines);
        setRouteIndex(0);
        const firstLine = lines[0];

        if (firstLine === undefined) {
            return;
        }

        setMessage(describe(chosenShape, viaName));
        fitLine(firstLine);
    }

    function chooseLoop(index: number): void {
        const line = routes[index];

        if (line === undefined || index === routeIndex) {
            return;
        }

        dirty.current = true;
        setRouteIndex(index);
        setMessage(describe(shape, viaName));
    }

    selectLoopRef.current = chooseLoop;

    function showAnother(): void {
        if (routes.length < 2) {
            return;
        }

        const next = (routeIndex + 1) % routes.length;
        const line = routes[next];
        chooseLoop(next);

        if (line !== undefined) {
            fitLine(line);
        }
    }

    function saveFile(blob: Blob, filename: string): void {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        URL.revokeObjectURL(url);
    }

    function download(): void {
        if (multisport) {
            if (plan !== null) {
                saveFile(
                    new Blob([multisportGpx(name, plan)], {
                        type: 'application/gpx+xml',
                    }),
                    'traceway.gpx',
                );
            }

            return;
        }

        if (preview === null) {
            return;
        }

        const blob = new Blob([gpx(name || 'Traceway', preview.coordinates)], {
            type: 'application/gpx+xml',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'traceway.gpx';
        link.click();
        URL.revokeObjectURL(url);
    }

    async function downloadLegFits(chosen: MultisportPlan): Promise<void> {
        for (const [index, leg] of chosen.legs.entries()) {
            if (leg.discipline === 'swim' || leg.coordinates.length < 2) {
                continue;
            }

            const response = await fetch('/routes/fit', {
                method: 'POST',
                headers: {
                    Accept: 'application/octet-stream',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    name: name || 'Traceway',
                    sport: leg.discipline,
                    leg: index + 1,
                    coordinates: leg.coordinates,
                }),
            });

            if (!response.ok) {
                setMessage(
                    `The course file for leg ${index + 1} could not be built.`,
                );

                return;
            }

            saveFile(
                await response.blob(),
                `traceway-${index + 1}-${leg.discipline}.fit`,
            );
        }

        setMessage(
            'One FIT course per leg. Load each into its leg of a multisport activity.',
        );
    }

    async function downloadFit(): Promise<void> {
        if (multisport) {
            if (plan !== null) {
                await downloadLegFits(plan);
            }

            return;
        }

        if (preview === null) {
            return;
        }

        const response = await fetch('/routes/fit', {
            method: 'POST',
            headers: {
                Accept: 'application/octet-stream',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                name: name || 'Traceway',
                sport,
                coordinates: preview.coordinates,
            }),
        });

        if (!response.ok) {
            setMessage('The course file could not be built.');

            return;
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'traceway.fit';
        link.click();
        URL.revokeObjectURL(url);
    }

    function savePayload(): Record<string, unknown> | null {
        if (!multisport) {
            return preview === null
                ? null
                : {
                      name: name.trim() || 'Traceway route',
                      sport,
                      surface,
                      elevation,
                      roads,
                      distance_metres: preview.distance_metres,
                      ascent_metres: preview.ascent_metres,
                      coordinates: preview.coordinates,
                      user_coordinates: preview.user_coordinates ?? null,
                      finish_coordinates: preview.finish_coordinates ?? null,
                  };
        }

        const first = legs[0];

        if (plan === null || first === undefined) {
            return null;
        }

        return {
            name: name.trim() || 'Traceway session',
            sport: first.sport,
            surface: first.surface,
            elevation: first.elevation,
            roads: first.roads,
            distance_metres: plan.distance_metres,
            ascent_metres: plan.ascent_metres,
            coordinates: joinedLine(plan),
            layout: plan.layout,
            legs: plan.legs,
        };
    }

    async function saveRoute(): Promise<void> {
        const payload = savePayload();

        if (payload === null || saving || routeSaved) {
            return;
        }

        setSaving(true);

        try {
            const response = await fetch('/routes/saved', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            });
            const body: unknown = await response.json().catch(() => null);
            const url =
                typeof body === 'object' &&
                body !== null &&
                'url' in body &&
                typeof body.url === 'string'
                    ? body.url
                    : null;

            if (!response.ok || url === null) {
                setMessage('The route could not be saved.');

                return;
            }

            setRouteSaved(true);
            setMessage('Saved. Share it from Saved routes.');
        } catch {
            setMessage('The route could not be saved.');
        } finally {
            setSaving(false);
        }
    }

    async function drawBuild(next?: {
        sport?: string;
        surface?: string;
        elevation?: string;
        roads?: 'quiet' | 'any';
        distanceKm?: number;
    }): Promise<void> {
        if (buildPoints.length === 0) {
            setMessage('Tap a road on the map to add a point.');

            return;
        }

        setRouteSaved(false);
        const chosenSport = next?.sport ?? sport;
        const chosenSurface = next?.surface ?? surface;
        const chosenElevation = next?.elevation ?? elevation;
        const chosenRoads = next?.roads ?? roads;
        const chosenDistance = next?.distanceKm ?? distanceKm;

        dirty.current = true;
        setBusy(true);
        setMessage('Drawing the route…');

        const response = await fetch('/routes/build', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                sport: chosenSport,
                surface: chosenSurface,
                elevation: chosenElevation,
                roads: chosenRoads,
                distance_metres: Math.round(chosenDistance * 1000),
                latitude,
                longitude,
                follow: followRoads,
                finish: finishLoop,
                points: buildPoints.map((point) => ({
                    latitude: point.latitude,
                    longitude: point.longitude,
                })),
            }),
        });
        const body: unknown = await response.json();
        setBusy(false);

        if (typeof body !== 'object' || body === null || !isPreview(body)) {
            const record =
                typeof body === 'object' && body !== null
                    ? (body as Record<string, unknown>)
                    : {};
            setMessage(
                typeof record.message === 'string'
                    ? record.message
                    : 'Those points did not join along the roads.',
            );

            return;
        }

        const record = body as Preview & {
            legs_metres?: number[];
            user_metres?: number;
            finish_metres?: number;
            finish_coordinates?: Coordinate[];
        };
        setRoutes([record]);
        setRouteIndex(0);
        setLegMetres(
            Array.isArray(record.legs_metres) ? record.legs_metres : [],
        );
        setMessage(
            finishLoop
                ? 'Your points are kept. Traceway closed the loop from the last point without doubling back.'
                : 'The line follows your points and stops at the last one.',
        );
        fitLine(record);
    }

    function planLegs(plan: MultisportPlan): Coordinate[][] {
        return plan.legs
            .filter((leg) => leg.discipline !== 'swim')
            .map((leg) => leg.coordinates);
    }

    async function drawMultisport(next?: {
        legs?: LandLeg[];
        layout?: Layout;
        swim?: Coordinate[] | null;
    }): Promise<void> {
        const chosenLegs = next?.legs ?? legs;
        const chosenLayout = next?.layout ?? layout;
        const chosenSwim = next?.swim === undefined ? swim : next.swim;
        const missing = chosenLegs.findIndex((leg) => leg.finish === null);

        if (chosenLayout === 'chain' && missing >= 0) {
            setActiveLeg(missing);
            setPlacing({ kind: 'finish', leg: missing });
            setMessage(
                `Tap the map where leg ${missing + (chosenSwim === null ? 1 : 2)} ends.`,
            );

            return;
        }

        setRouteSaved(false);
        setPlacing(null);
        dirty.current = true;
        setBusy(true);
        setMessage('Drawing every leg…');

        try {
            const response = await fetch('/routes/multisport', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    layout: chosenLayout,
                    latitude,
                    longitude,
                    ...(chosenSwim === null || chosenSwim.length === 0
                        ? {}
                        : { swim: { coordinates: chosenSwim } }),
                    legs: chosenLegs.map((leg) => ({
                        sport: leg.sport,
                        surface: leg.surface,
                        elevation: leg.elevation,
                        roads: leg.roads,
                        distance_metres: Math.round(leg.distanceKm * 1000),
                        ...(chosenLayout === 'chain' && leg.finish !== null
                            ? {
                                  finish_latitude: leg.finish[1],
                                  finish_longitude: leg.finish[0],
                              }
                            : {}),
                    })),
                }),
            });
            const body: unknown = await response.json().catch(() => null);

            if (!response.ok || !isPlan(body)) {
                const record =
                    typeof body === 'object' && body !== null
                        ? (body as Record<string, unknown>)
                        : {};
                setMessage(
                    typeof record.message === 'string'
                        ? record.message
                        : 'One of the legs did not come back.',
                );

                return;
            }

            setPlan(body);
            setMessage(
                chosenLayout === 'loops'
                    ? 'Every leg leaves the transition and comes back to it. Arrows point the way you travel.'
                    : 'Each leg ends where the next one starts. Arrows point the way you travel.',
            );
            fitLine(
                {
                    distance_metres: body.distance_metres,
                    ascent_metres: body.ascent_metres,
                    coordinates: joinedLine(body),
                },
                180,
                planLegs(body),
            );
        } catch {
            setMessage('One of the legs did not come back.');
        } finally {
            setBusy(false);
        }
    }

    function changeLegs(nextLegs: LandLeg[]): void {
        dirty.current = true;
        setLegs(nextLegs);
        setActiveLeg((current) => Math.min(current, nextLegs.length - 1));

        if (plan !== null) {
            void drawMultisport({ legs: nextLegs });
        }
    }

    function updateLeg(patch: Partial<Omit<LandLeg, 'id'>>): void {
        setSheet(null);
        changeLegs(
            legs.map((leg, index) =>
                index === activeLeg ? { ...leg, ...patch } : leg,
            ),
        );
    }

    function choosePreset(value: (typeof presets)[number]['value']): void {
        const preset = presets.find((item) => item.value === value);

        if (preset === undefined) {
            return;
        }

        const nextLegs = withIds(preset.legs);
        const nextSwim = preset.swim ? (swim ?? []) : null;
        dirty.current = true;
        setLegs(nextLegs);
        setSwim(nextSwim);
        setActiveLeg(0);
        setPlan(null);
        setPlacing(
            nextSwim !== null && nextSwim.length === 0
                ? { kind: 'swim' }
                : null,
        );
        setName(preset.label);
        setMessage(
            nextSwim !== null && nextSwim.length === 0
                ? 'Tap the water to draw the swim, then draw the route.'
                : `${preset.label}. Draw the route when you are ready.`,
        );
    }

    function chooseLayout(value: Layout): void {
        if (value === layout) {
            return;
        }

        dirty.current = true;
        setLayout(value);
        setPlan(null);

        if (value === 'chain') {
            const missing = legs.findIndex((leg) => leg.finish === null);

            if (missing >= 0) {
                setActiveLeg(missing);
                setPlacing({ kind: 'finish', leg: missing });
                setMessage(
                    `Tap the map where leg ${missing + (swim === null ? 1 : 2)} ends.`,
                );

                return;
            }
        }

        setPlacing(null);
        setMessage('Draw the route when you are ready.');
    }

    function toggleSwim(enabled: boolean): void {
        dirty.current = true;
        setSwim(enabled ? [] : null);
        setPlan(null);
        setPlacing(enabled ? { kind: 'swim' } : null);
        setMessage(
            enabled
                ? 'Tap the water to draw the swim. Tap once for a pin.'
                : 'Swim removed.',
        );
    }

    function drawSwim(): void {
        if (placing?.kind === 'swim') {
            setPlacing(null);
            setMessage('Swim kept. Draw the route when you are ready.');

            return;
        }

        dirty.current = true;
        setSwim([]);
        setPlan(null);
        setPlacing({ kind: 'swim' });
        setMessage('Tap the water to draw the swim. Tap once for a pin.');
    }

    function addLeg(): void {
        if (legs.length >= mostLandLegs) {
            return;
        }

        const last = legs[legs.length - 1];
        const nextLegs = [
            ...legs,
            newLeg(last !== undefined && last.sport === 'run' ? 'road' : 'run'),
        ];
        dirty.current = true;
        setLegs(nextLegs);
        setActiveLeg(nextLegs.length - 1);
        setPlan(null);

        if (layout === 'chain') {
            setPlacing({ kind: 'finish', leg: nextLegs.length - 1 });
        }
    }

    function removeLeg(index: number): void {
        if (legs.length < 2) {
            return;
        }

        setPlacing(null);
        changeLegs(legs.filter((_, item) => item !== index));
    }

    function moveLeg(from: number, to: number): void {
        if (to < 0 || to >= legs.length || from === to) {
            return;
        }

        const nextLegs = [...legs];
        const [item] = nextLegs.splice(from, 1);

        if (item === undefined) {
            return;
        }

        nextLegs.splice(to, 0, item);
        setActiveLeg(to);
        changeLegs(nextLegs);
    }

    function placeFinish(index: number): void {
        setActiveLeg(index);
        setPlacing({ kind: 'finish', leg: index });
        setMessage(
            `Tap the map where leg ${index + (swim === null ? 1 : 2)} ends.`,
        );
    }

    function clickMultisport(lng: number, lat: number): void {
        const point: Coordinate = [lng, lat];
        dirty.current = true;

        if (placing?.kind === 'swim') {
            const nextSwim = [...(swim ?? []), point].slice(0, mostSwimPoints);
            setSwim(nextSwim);
            setPlan(null);
            setMessage(
                nextSwim.length >= mostSwimPoints
                    ? 'That is the most points a swim can have.'
                    : 'Keep tapping to draw the swim. Tap the swim row when it is done.',
            );

            return;
        }

        if (placing?.kind === 'finish') {
            const nextLegs = legs.map((leg, index) =>
                index === placing.leg ? { ...leg, finish: point } : leg,
            );
            const missing = nextLegs.findIndex((leg) => leg.finish === null);
            setLegs(nextLegs);
            setPlan(null);

            if (missing >= 0) {
                setActiveLeg(missing);
                setPlacing({ kind: 'finish', leg: missing });
                setMessage(
                    `Tap the map where leg ${missing + (swim === null ? 1 : 2)} ends.`,
                );

                return;
            }

            setPlacing(null);
            void drawMultisport({ legs: nextLegs });

            return;
        }

        setLongitude(lng);
        setLatitude(lat);
        setPlan(null);
        setMessage('Transition moved. Draw the route when you are ready.');
    }

    function showSnapRing(point: RoadSnap | null): void {
        const map = mapRef.current;

        if (map === null || point === null || modeRef.current !== 'build') {
            snapMarkerRef.current?.remove();
            snapMarkerRef.current = null;

            return;
        }

        if (snapMarkerRef.current === null) {
            const element = document.createElement('div');
            element.className = 'snap-ring';
            element.style.pointerEvents = 'none';
            snapMarkerRef.current = new maplibregl.Marker({
                element,
                anchor: 'center',
            })
                .setLngLat([point.longitude, point.latitude])
                .addTo(map);

            return;
        }

        snapMarkerRef.current.setLngLat([point.longitude, point.latitude]);
    }

    hideSnapRef.current = () => {
        if (snapFrameRef.current !== 0) {
            cancelAnimationFrame(snapFrameRef.current);
            snapFrameRef.current = 0;
        }

        snapCursorRef.current = null;
        hoverSnapRef.current = null;
        snapMarkerRef.current?.remove();
        snapMarkerRef.current = null;
    };

    moveSnapRef.current = (lng, lat) => {
        snapCursorRef.current = { lng, lat };

        if (snapFrameRef.current !== 0) {
            return;
        }

        snapFrameRef.current = requestAnimationFrame(() => {
            snapFrameRef.current = 0;
            const cursor = snapCursorRef.current;
            const map = mapRef.current;

            if (
                cursor === null ||
                map === null ||
                modeRef.current !== 'build'
            ) {
                return;
            }

            const point = snapToRoad(map, cursor.lng, cursor.lat);
            hoverSnapRef.current = {
                queryLng: cursor.lng,
                queryLat: cursor.lat,
                point,
            };
            showSnapRing(point);
        });
    };

    function placeBuildPoint(lng: number, lat: number): void {
        const map = mapRef.current;
        const point = map === null ? null : snapToRoad(map, lng, lat);

        if (point === null) {
            setMessage('No road or path is close enough to put a point there.');

            return;
        }

        const placed = {
            id: crypto.randomUUID(),
            longitude: point.longitude,
            latitude: point.latitude,
            name: 'Junction',
        };
        dirty.current = true;
        setBuildPoints((current) => [...current, placed]);
        void labelPlace(point.longitude, point.latitude).then((name) => {
            setBuildPoints((current) =>
                current.map((item) =>
                    item.id === placed.id ? { ...item, name } : item,
                ),
            );
        });
    }

    onMapClickRef.current = (lng, lat) => {
        if (mode === 'multisport') {
            clickMultisport(lng, lat);

            return;
        }

        if (mode === 'build') {
            placeBuildPoint(lng, lat);

            return;
        }

        if (placingViaRef.current) {
            const point: Coordinate = [lng, lat];
            dirty.current = true;
            setVia(point);
            setViaName(null);
            setPlacingVia(false);
            void labelPlace(lng, lat).then(setViaName);
            void generate({ via: point });

            return;
        }

        dirty.current = true;
        setLongitude(lng);
        setLatitude(lat);
        setRoutes([]);
        setRouteIndex(0);
        setMessage('Start moved. Draw a route when you are ready.');
    };

    function reorderPoints(from: number, to: number): void {
        const list = pointsListRef.current;

        if (
            from === to ||
            from < 0 ||
            to < 0 ||
            from >= buildPoints.length ||
            to >= buildPoints.length
        ) {
            list?.querySelectorAll<HTMLElement>('[data-point]').forEach(
                (node) => {
                    node.style.transform = '';
                    node.style.transition = '';
                    node.classList.remove('lift');
                },
            );

            return;
        }

        if (list !== null) {
            const before = new Map<string, number>();

            list.querySelectorAll<HTMLElement>('[data-point]').forEach(
                (node) => {
                    const id = node.dataset.point;

                    if (id !== undefined) {
                        before.set(id, node.getBoundingClientRect().top);
                    }

                    node.style.transform = '';
                    node.style.transition = '';
                    node.classList.remove('lift');
                },
            );
            pointShiftRef.current = before;
        }

        setBuildPoints((current) => {
            const next = [...current];
            const [item] = next.splice(from, 1);

            if (item === undefined) {
                return current;
            }

            next.splice(to, 0, item);

            return next;
        });
    }

    function startPointDrag(event: ReactPointerEvent<HTMLButtonElement>): void {
        if (event.button !== 0 || buildPoints.length < 2) {
            return;
        }

        const grip = event.currentTarget;
        const row = grip.closest('.pt');
        const list = pointsListRef.current;

        if (!(row instanceof HTMLElement) || list === null) {
            return;
        }

        event.preventDefault();
        grip.setPointerCapture(event.pointerId);

        const rows = [
            ...list.querySelectorAll<HTMLElement>(':scope > .pt[data-point]'),
        ];
        const origin = rows.indexOf(row);

        if (origin < 0) {
            return;
        }

        const tops = rows.map((item) => item.getBoundingClientRect().top);
        const height = row.getBoundingClientRect().height;
        const above = tops[0];
        const below = tops[1];
        const pitch =
            above !== undefined && below !== undefined
                ? below - above
                : height + 8;
        let slot = origin;
        row.classList.add('lift');

        const place = (clientY: number) => {
            const delta = clientY - event.clientY;
            const clamped = Math.max(
                -origin * pitch,
                Math.min((rows.length - 1 - origin) * pitch, delta),
            );
            row.style.transform = `translateY(${clamped}px)`;
            const nextSlot = Math.max(
                0,
                Math.min(rows.length - 1, Math.round(origin + clamped / pitch)),
            );

            if (nextSlot === slot) {
                return;
            }

            slot = nextSlot;

            rows.forEach((item, index) => {
                if (item === row) {
                    return;
                }

                let shift = 0;

                if (origin < slot && index > origin && index <= slot) {
                    shift = -pitch;
                }

                if (slot < origin && index >= slot && index < origin) {
                    shift = pitch;
                }

                item.style.transform =
                    shift === 0 ? '' : `translateY(${shift}px)`;
            });
        };

        const move = (pointer: PointerEvent) => {
            place(pointer.clientY);
        };
        const end = (pointer: PointerEvent) => {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            place(pointer.clientY);
            reorderPoints(origin, slot);
        };

        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
    }

    function redraw(next: {
        sport?: string;
        surface?: string;
        elevation?: string;
        distanceKm?: number;
        shape?: string;
        via?: Coordinate | null;
        roads?: 'quiet' | 'any';
    }): void {
        if (mode === 'build') {
            if (buildPoints.length > 0) {
                void drawBuild(next);
            }

            return;
        }

        void generate(next);
    }

    function chooseSport(value: string): void {
        if (multisport) {
            updateLeg({ sport: value });

            return;
        }

        setSport(value);
        setSheet(null);
        redraw({ sport: value });
    }

    function chooseSurface(value: string): void {
        if (multisport) {
            updateLeg({ surface: value });

            return;
        }

        setSurface(value);
        setSheet(null);
        redraw({ surface: value });
    }

    function chooseElevation(value: string): void {
        if (multisport) {
            updateLeg({ elevation: value });

            return;
        }

        setElevation(value);
        setSheet(null);
        redraw({ elevation: value });
    }

    function chooseShape(value: string): void {
        setShape(value);
        setSheet(null);
        void generate({ shape: value });
    }

    useEffect(() => {
        const map = mapRef.current;
        const next = mapStyleUrl(mapStyle, resolvedAppearance);

        if (map === null || appliedStyleRef.current === next) {
            return;
        }

        appliedStyleRef.current = next;
        map.setStyle(next);
    }, [mapStyle, resolvedAppearance]);

    function chooseMap(id: string): void {
        if (!mapStyles.some((item) => item.id === id)) {
            return;
        }

        dirty.current = true;
        setMapStyle(id);
        setSheet(null);
    }

    return (
        <>
            <Head title="Planner" />
            <div className="tw-planner" data-theme={resolvedAppearance}>
                <header className="hdr">
                    <Link
                        className="brand"
                        href="/dashboard"
                        aria-current="page"
                        aria-label="Traceway"
                    >
                        <svg
                            width="26"
                            height="26"
                            viewBox="0 0 28 28"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M6 23 V12 a6 6 0 0 1 6 -6 h4 a6 6 0 0 1 6 6 v3"
                                stroke="currentColor"
                                strokeWidth="3"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                            <circle
                                cx="22"
                                cy="22"
                                r="3"
                                fill="oklch(0.78 0.17 128)"
                            />
                        </svg>
                        <span>Traceway</span>
                    </Link>
                    <div className="hdr-end">
                        <Link
                            className="plan-nav"
                            href="/dashboard"
                            aria-current="page"
                        >
                            <span className="long">Plan your route</span>
                            <span className="short">Plan</span>
                        </Link>
                        <Link
                            className="saved-nav"
                            href="/saved"
                            aria-label="Saved routes"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 16 16"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 2.5h8a1 1 0 0 1 1 1V14l-5-2.5L3 14V3.5a1 1 0 0 1 1-1z"
                                    stroke="currentColor"
                                    strokeWidth="1.4"
                                    strokeLinejoin="round"
                                />
                            </svg>
                            <span>Saved routes</span>
                        </Link>
                        <Link
                            className="acct"
                            href="/settings/profile"
                            aria-label="Account and settings"
                        >
                            <span className="name">{rider}</span>
                            <span className="avatar">{riderInitials}</span>
                        </Link>
                    </div>
                </header>
                <main className="app">
                    <div
                        className={
                            mode === 'build' ? 'mapwrap build' : 'mapwrap'
                        }
                    >
                        <div ref={mapNode} className="h-full w-full" />
                        <div className="vig" />
                        <div className="mapctl">
                            <div className="ctl">
                                <button
                                    type="button"
                                    aria-label="Zoom in"
                                    onClick={() => mapRef.current?.zoomIn()}
                                >
                                    +
                                </button>
                                <button
                                    type="button"
                                    aria-label="Zoom out"
                                    onClick={() => mapRef.current?.zoomOut()}
                                >
                                    −
                                </button>
                            </div>
                            <div className="ctl">
                                <button
                                    type="button"
                                    aria-label="Fit the route"
                                    onClick={() => fitRouteRef.current(180)}
                                >
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M8 2 L10 6 H6 Z M8 14 L6 10 H10 Z M2 8 L6 6 V10 Z M14 8 L10 10 V6 Z"
                                            fill="currentColor"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div className="ctl">
                                <button
                                    type="button"
                                    aria-label="Find my location"
                                    onClick={locateMe}
                                >
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <circle
                                            cx="8"
                                            cy="8"
                                            r="3"
                                            stroke="currentColor"
                                            strokeWidth="1.6"
                                        />
                                        <path
                                            d="M8 1.5 V4 M8 12 V14.5 M1.5 8 H4 M12 8 H14.5"
                                            stroke="currentColor"
                                            strokeWidth="1.6"
                                            strokeLinecap="round"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        {mode === 'build' ? (
                            <p className="maphint">
                                <b>+</b> The ring sits on the nearest road or
                                path
                            </p>
                        ) : null}
                        {multisport && placing !== null ? (
                            <p className="maphint">
                                <b>+</b>{' '}
                                {placing.kind === 'swim'
                                    ? 'Tap the water to draw the swim'
                                    : 'Tap where this leg ends'}
                            </p>
                        ) : null}
                        <p className="attr">
                            MapLibre · OpenFreeMap · © OpenMapTiles · Data from
                            OpenStreetMap
                        </p>
                    </div>
                    <aside
                        ref={panelRef}
                        className={panelOpen ? 'panel open' : 'panel'}
                        aria-label="Route plan"
                    >
                        <button
                            type="button"
                            className="handle"
                            aria-expanded={panelOpen}
                            aria-label={
                                panelOpen
                                    ? 'Collapse the plan'
                                    : 'Expand the plan'
                            }
                            {...drawerProps(
                                panelOpen,
                                setPanelOpen,
                                panelRef,
                                drawerDrag,
                            )}
                        />
                        <div className="phead">
                            <button
                                type="button"
                                className="summary"
                                {...drawerProps(
                                    panelOpen,
                                    setPanelOpen,
                                    panelRef,
                                    drawerDrag,
                                )}
                            >
                                <span>
                                    <b>
                                        {shown
                                            ? `${(shown.distance_metres / 1000).toFixed(1)} km`
                                            : multisport
                                              ? `${legs.reduce((total, leg) => total + leg.distanceKm, 0)} km`
                                              : `${distanceKm} km`}
                                    </b>
                                    {multisport
                                        ? ` · ${legs.length + (swim === null ? 0 : 1)} legs · ${layout === 'loops' ? 'Loops from T' : 'Chain'}`
                                        : routes.length > 0
                                          ? ` · Loop ${routeIndex + 1} of ${routes.length}`
                                          : ` · ${shapeLabel}`}
                                    {multisport ? '' : ` · ${surfaceLabel}`}
                                </span>
                            </button>
                            <div className="title-row">
                                <span className="icon-box" aria-hidden="true">
                                    <SportIcon className="size-4" />
                                </span>
                                {renaming ? (
                                    <>
                                        <label
                                            className="vh"
                                            htmlFor="route-name"
                                        >
                                            Route name
                                        </label>
                                        <input
                                            id="route-name"
                                            ref={nameInputRef}
                                            type="text"
                                            value={name}
                                            autoComplete="off"
                                            spellCheck={false}
                                            onChange={(event) => {
                                                dirty.current = true;
                                                setName(event.target.value);
                                            }}
                                            onBlur={() => {
                                                const trimmed = name.trim();

                                                if (trimmed === '') {
                                                    setName(
                                                        nameBeforeEdit.current,
                                                    );
                                                } else if (trimmed !== name) {
                                                    setName(trimmed);
                                                }

                                                setRenaming(false);
                                            }}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Escape') {
                                                    setName(
                                                        nameBeforeEdit.current,
                                                    );
                                                    setRenaming(false);

                                                    return;
                                                }

                                                if (event.key === 'Enter') {
                                                    event.currentTarget.blur();
                                                }
                                            }}
                                        />
                                    </>
                                ) : (
                                    <h2 className="route-title">{name}</h2>
                                )}
                                <button
                                    type="button"
                                    className="ghostbtn"
                                    aria-label={
                                        renaming
                                            ? 'Done renaming'
                                            : 'Rename route'
                                    }
                                    onMouseDown={(event) => {
                                        if (renaming) {
                                            event.preventDefault();
                                        }
                                    }}
                                    onClick={() => {
                                        if (renaming) {
                                            nameInputRef.current?.blur();

                                            return;
                                        }

                                        nameBeforeEdit.current = name;
                                        setRenaming(true);
                                    }}
                                >
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M9.5 3.5 L12.5 6.5 L6 13 H3 V10 Z"
                                            stroke="currentColor"
                                            strokeWidth="1.4"
                                            strokeLinejoin="round"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div
                                className="seg"
                                role="tablist"
                                aria-label="Planning mode"
                            >
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={mode === 'generate'}
                                    onClick={() => {
                                        setPlacing(null);
                                        setMode('generate');
                                    }}
                                >
                                    Generate
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={mode === 'build'}
                                    onClick={() => {
                                        setPlacing(null);
                                        setMode('build');
                                    }}
                                >
                                    Build
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={mode === 'multisport'}
                                    onClick={() => {
                                        setMode('multisport');
                                        setMessage(
                                            plan === null
                                                ? 'Pick a session, set the transition, then draw.'
                                                : 'Every leg, in order.',
                                        );
                                    }}
                                >
                                    Multisport
                                </button>
                            </div>
                        </div>
                        <div className="pbody">
                            {multisport ? (
                                <MultisportLegs
                                    legs={legs}
                                    layout={layout}
                                    swim={swim}
                                    activeLeg={activeLeg}
                                    placing={placing}
                                    labels={{
                                        sport: (value) =>
                                            sports.find(
                                                (item) => item.value === value,
                                            )?.label ?? value,
                                        surface: (value) =>
                                            surfaces.find(
                                                (item) => item.value === value,
                                            )?.label ?? value,
                                        elevation: (value) =>
                                            elevations.find(
                                                (item) => item.value === value,
                                            )?.label ?? value,
                                        roads: (value) =>
                                            roadsOptions.find(
                                                (item) => item.value === value,
                                            )?.label ?? value,
                                    }}
                                    onPreset={choosePreset}
                                    onLayout={chooseLayout}
                                    onSwim={toggleSwim}
                                    onDrawSwim={drawSwim}
                                    onSelect={(index) => {
                                        setActiveLeg(index);

                                        if (
                                            layout === 'chain' &&
                                            placing?.kind === 'finish'
                                        ) {
                                            setPlacing({
                                                kind: 'finish',
                                                leg: index,
                                            });
                                        }
                                    }}
                                    onOpen={(next: LegSheet) => setSheet(next)}
                                    onAdd={addLeg}
                                    onRemove={removeLeg}
                                    onMove={moveLeg}
                                    onPlaceFinish={placeFinish}
                                />
                            ) : mode === 'generate' ? (
                                <section aria-label="Ask">
                                    <p className="sec-label">Ask</p>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('shape')}
                                    >
                                        <span className="k">Shape</span>
                                        <span className="v">
                                            {shapeLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('length')}
                                    >
                                        <span className="k">Length</span>
                                        <span className="v">
                                            {distanceKm} km
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('sport')}
                                    >
                                        <span className="k">Sport</span>
                                        <span className="v">
                                            {sportLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('surface')}
                                    >
                                        <span className="k">Surface</span>
                                        <span className="v">
                                            {surfaceLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('elevation')}
                                    >
                                        <span className="k">Hills</span>
                                        <span className="v">
                                            {elevationLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('roads')}
                                    >
                                        <span className="k">Roads</span>
                                        <span className="v">
                                            {roadsLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => {
                                            if (via !== null) {
                                                setVia(null);
                                                setViaName(null);
                                                setPlacingVia(false);
                                                void generate({ via: null });
                                                return;
                                            }
                                            setPlacingVia(true);
                                            setMessage(
                                                'Tap the map where the loop should pass.',
                                            );
                                        }}
                                    >
                                        <span className="k">Via</span>
                                        <span className="v">
                                            {via === null ? (
                                                placingVia ? (
                                                    'Tap the map'
                                                ) : (
                                                    'None'
                                                )
                                            ) : (
                                                <>
                                                    <span className="dot" />
                                                    {viaName ?? 'Set'}
                                                </>
                                            )}
                                            <Chevron />
                                        </span>
                                    </button>
                                </section>
                            ) : (
                                <section aria-label="Points">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <p className="sec-label">Points</p>
                                        <p className="small">
                                            Tap a road on the map to add one
                                        </p>
                                    </div>
                                    <div className="pts" ref={pointsListRef}>
                                        <div className="pt">
                                            <span
                                                className="mark start"
                                                aria-hidden="true"
                                            >
                                                <span />
                                            </span>
                                            <button
                                                type="button"
                                                className="field"
                                                onClick={() =>
                                                    showPoint(
                                                        longitude,
                                                        latitude,
                                                    )
                                                }
                                            >
                                                <span>Your pin</span>
                                                <small>Start</small>
                                                <span className="vh">
                                                    Show on the map
                                                </span>
                                            </button>
                                            <button
                                                type="button"
                                                className="ghostbtn"
                                                aria-label="Use my location as the start"
                                                onClick={locateMe}
                                            >
                                                <svg
                                                    width="16"
                                                    height="16"
                                                    viewBox="0 0 16 16"
                                                    fill="none"
                                                    aria-hidden="true"
                                                >
                                                    <circle
                                                        cx="8"
                                                        cy="8"
                                                        r="4"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                    />
                                                    <path
                                                        d="M8 1 V3.5 M8 12.5 V15 M1 8 H3.5 M12.5 8 H15"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                        {buildPoints.map((point, index) => (
                                            <div
                                                className="pt"
                                                data-point={point.id}
                                                key={point.id}
                                            >
                                                <span
                                                    className="mark"
                                                    aria-hidden="true"
                                                >
                                                    {index + 1}
                                                </span>
                                                <button
                                                    type="button"
                                                    className="field"
                                                    onClick={() =>
                                                        showPoint(
                                                            point.longitude,
                                                            point.latitude,
                                                        )
                                                    }
                                                >
                                                    <span>{point.name}</span>
                                                    <small>
                                                        {typeof legMetres[
                                                            index
                                                        ] === 'number'
                                                            ? `${(legMetres[index] / 1000).toFixed(1)} km`
                                                            : `${(
                                                                  metresBetween(
                                                                      index ===
                                                                          0
                                                                          ? [
                                                                                longitude,
                                                                                latitude,
                                                                            ]
                                                                          : [
                                                                                buildPoints[
                                                                                    index -
                                                                                        1
                                                                                ]
                                                                                    ?.longitude ??
                                                                                    longitude,
                                                                                buildPoints[
                                                                                    index -
                                                                                        1
                                                                                ]
                                                                                    ?.latitude ??
                                                                                    latitude,
                                                                            ],
                                                                      [
                                                                          point.longitude,
                                                                          point.latitude,
                                                                      ],
                                                                  ) / 1000
                                                              ).toFixed(1)} km`}
                                                    </small>
                                                    <span className="vh">
                                                        Show on the map
                                                    </span>
                                                </button>
                                                <button
                                                    type="button"
                                                    className="grip"
                                                    aria-label={`Move point ${index + 1}. Drag, or use the arrow keys.`}
                                                    onPointerDown={(event) =>
                                                        startPointDrag(event)
                                                    }
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key !==
                                                                'ArrowUp' &&
                                                            event.key !==
                                                                'ArrowDown'
                                                        ) {
                                                            return;
                                                        }

                                                        event.preventDefault();
                                                        reorderPoints(
                                                            index,
                                                            event.key ===
                                                                'ArrowUp'
                                                                ? index - 1
                                                                : index + 1,
                                                        );
                                                    }}
                                                >
                                                    <svg
                                                        width="14"
                                                        height="14"
                                                        viewBox="0 0 14 14"
                                                        fill="currentColor"
                                                        aria-hidden="true"
                                                    >
                                                        <circle
                                                            cx="4.5"
                                                            cy="2.5"
                                                            r="1.3"
                                                        />
                                                        <circle
                                                            cx="9.5"
                                                            cy="2.5"
                                                            r="1.3"
                                                        />
                                                        <circle
                                                            cx="4.5"
                                                            cy="7"
                                                            r="1.3"
                                                        />
                                                        <circle
                                                            cx="9.5"
                                                            cy="7"
                                                            r="1.3"
                                                        />
                                                        <circle
                                                            cx="4.5"
                                                            cy="11.5"
                                                            r="1.3"
                                                        />
                                                        <circle
                                                            cx="9.5"
                                                            cy="11.5"
                                                            r="1.3"
                                                        />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    className="ghostbtn"
                                                    aria-label={`Remove point ${index + 1}`}
                                                    onClick={() =>
                                                        setBuildPoints(
                                                            (current) =>
                                                                current.filter(
                                                                    (_, item) =>
                                                                        item !==
                                                                        index,
                                                                ),
                                                        )
                                                    }
                                                >
                                                    <svg
                                                        width="14"
                                                        height="14"
                                                        viewBox="0 0 16 16"
                                                        fill="none"
                                                        aria-hidden="true"
                                                    >
                                                        <path
                                                            d="M4 4 L12 12 M12 4 L4 12"
                                                            stroke="currentColor"
                                                            strokeWidth="1.6"
                                                            strokeLinecap="round"
                                                        />
                                                    </svg>
                                                </button>
                                            </div>
                                        ))}
                                        <div className="pt endrow">
                                            <span
                                                className="mark end"
                                                aria-hidden="true"
                                            />
                                            <button
                                                type="button"
                                                className="field dash"
                                                onClick={() =>
                                                    setFinishLoop(
                                                        (current) => !current,
                                                    )
                                                }
                                            >
                                                <span>
                                                    {finishLoop
                                                        ? 'End · back at the start'
                                                        : 'End · at your last point'}
                                                </span>
                                                <small>Change</small>
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            className="addrow"
                                            onClick={() =>
                                                setMessage(
                                                    'Tap a road on the map. The point snaps onto the route.',
                                                )
                                            }
                                        >
                                            <span
                                                className="mark add"
                                                aria-hidden="true"
                                            >
                                                +
                                            </span>
                                            <span>Add a point</span>
                                        </button>
                                    </div>
                                </section>
                            )}
                            {mode === 'build' ? (
                                <section aria-label="Route">
                                    <p className="sec-label">Route</p>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('length')}
                                    >
                                        <span className="k">Length</span>
                                        <span className="v">
                                            {distanceKm} km
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('sport')}
                                    >
                                        <span className="k">Sport</span>
                                        <span className="v">
                                            {sportLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('surface')}
                                    >
                                        <span className="k">Surface</span>
                                        <span className="v">
                                            {surfaceLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('elevation')}
                                    >
                                        <span className="k">Hills</span>
                                        <span className="v">
                                            {elevationLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="row"
                                        onClick={() => setSheet('roads')}
                                    >
                                        <span className="k">Roads</span>
                                        <span className="v">
                                            {roadsLabel}
                                            <Chevron />
                                        </span>
                                    </button>
                                </section>
                            ) : null}
                            {mode === 'build' ? (
                                <section
                                    className="opts"
                                    aria-label="Build options"
                                >
                                    <div className="opt">
                                        <label htmlFor="opt-follow">
                                            Follow roads between points
                                        </label>
                                        <button
                                            type="button"
                                            className="sw"
                                            id="opt-follow"
                                            role="switch"
                                            aria-checked={followRoads}
                                            aria-label="Follow roads between points"
                                            onClick={() =>
                                                setFollowRoads(
                                                    (current) => !current,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="opt">
                                        <label htmlFor="opt-finish">
                                            Let Traceway finish the loop
                                        </label>
                                        <button
                                            type="button"
                                            className="sw"
                                            id="opt-finish"
                                            role="switch"
                                            aria-checked={finishLoop}
                                            aria-label="Let Traceway finish the loop"
                                            onClick={() =>
                                                setFinishLoop(
                                                    (current) => !current,
                                                )
                                            }
                                        />
                                    </div>
                                    <p
                                        className="small"
                                        style={{ marginTop: 8 }}
                                    >
                                        Your points are kept. Traceway routes
                                        between them along roads, then closes
                                        the loop from the last point with the
                                        same rules as Ask, without doubling
                                        back.
                                    </p>
                                </section>
                            ) : null}
                            <button
                                type="button"
                                className="fill"
                                disabled={busy}
                                onClick={() =>
                                    void (multisport
                                        ? drawMultisport()
                                        : mode === 'build'
                                          ? drawBuild()
                                          : generate())
                                }
                            >
                                {busy
                                    ? 'Drawing…'
                                    : shown
                                      ? 'Draw again'
                                      : 'Draw route'}
                            </button>
                            <section className="result" aria-label="Result">
                                <div className="top">
                                    <p
                                        className="sec-label"
                                        style={{ margin: 0 }}
                                    >
                                        Result
                                    </p>
                                    {mode === 'generate' ? (
                                        <div className="stepper">
                                            <button
                                                type="button"
                                                aria-label="Previous loop"
                                                disabled={
                                                    busy || routes.length < 2
                                                }
                                                onClick={showAnother}
                                            >
                                                ‹
                                            </button>
                                            <span>
                                                {routes.length > 0 ? (
                                                    <>
                                                        <i
                                                            aria-hidden="true"
                                                            style={{
                                                                background:
                                                                    routePaint(
                                                                        routeIndex,
                                                                    ).line,
                                                            }}
                                                        />
                                                        {`Loop ${routeIndex + 1} of ${routes.length}`}
                                                    </>
                                                ) : (
                                                    'Loop – of 3'
                                                )}
                                            </span>
                                            <button
                                                type="button"
                                                aria-label="Next loop"
                                                disabled={
                                                    busy || routes.length < 2
                                                }
                                                onClick={showAnother}
                                            >
                                                ›
                                            </button>
                                        </div>
                                    ) : null}
                                </div>
                                <div className="big">
                                    <b>
                                        {shown
                                            ? `${(shown.distance_metres / 1000).toFixed(1)} km`
                                            : '0.0 km'}
                                    </b>
                                    <span>
                                        {multisport
                                            ? plan === null
                                                ? 'Set the session, then draw.'
                                                : `${plan.ascent_metres > 0 ? `${plan.ascent_metres} m ascent · ` : ''}${plan.legs.length} legs`
                                            : preview
                                              ? `${preview.ascent_metres > 0 ? `${preview.ascent_metres} m ascent · ` : ''}${surfaceLabel} · ${elevationLabel === 'Any' ? 'any hills' : elevationLabel}`
                                              : 'Set your ask, then draw.'}
                                    </span>
                                </div>
                                {multisport && plan !== null ? (
                                    <ol className="leg-list">
                                        {plan.legs.map((leg, index) => (
                                            <li key={index}>
                                                <i
                                                    aria-hidden="true"
                                                    className={
                                                        leg.discipline ===
                                                        'swim'
                                                            ? 'dotted'
                                                            : undefined
                                                    }
                                                    style={{
                                                        background: legPaint(
                                                            leg.discipline,
                                                        ).line,
                                                        color: legPaint(
                                                            leg.discipline,
                                                        ).line,
                                                    }}
                                                />
                                                <b>
                                                    {leg.discipline === 'swim'
                                                        ? 'Swim'
                                                        : (sports.find(
                                                              (item) =>
                                                                  item.value ===
                                                                  leg.discipline,
                                                          )?.label ??
                                                          leg.discipline)}
                                                </b>
                                                <span>
                                                    {`${(leg.distance_metres / 1000).toFixed(1)} km`}
                                                    {leg.ascent_metres > 0
                                                        ? ` · ${leg.ascent_metres} m`
                                                        : ''}
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                ) : null}
                                {mode === 'build' && preview ? (
                                    <div className="legend">
                                        <span>
                                            <i />
                                            Your points,{' '}
                                            {(
                                                (preview.user_metres ??
                                                    preview.distance_metres) /
                                                1000
                                            ).toFixed(1)}{' '}
                                            km
                                        </span>
                                        {finishLoop ? (
                                            <span>
                                                <i className="dotted" />
                                                Traceway’s part,{' '}
                                                {(
                                                    (preview.finish_metres ??
                                                        0) / 1000
                                                ).toFixed(1)}{' '}
                                                km
                                            </span>
                                        ) : null}
                                    </div>
                                ) : null}
                                <p className="note" role="status">
                                    {settledStatus.current}
                                </p>
                                <div className="btns">
                                    <button
                                        type="button"
                                        className="outline"
                                        disabled={shown === null}
                                        onClick={download}
                                    >
                                        Export GPX
                                    </button>
                                    <button
                                        type="button"
                                        className="outline"
                                        disabled={shown === null}
                                        onClick={() => void downloadFit()}
                                    >
                                        Export FIT
                                    </button>
                                </div>
                                <div className="btns one">
                                    <button
                                        type="button"
                                        className="outline"
                                        disabled={
                                            shown === null ||
                                            saving ||
                                            routeSaved
                                        }
                                        onClick={() => void saveRoute()}
                                    >
                                        {saving
                                            ? 'Saving…'
                                            : routeSaved
                                              ? 'Saved'
                                              : 'Save to profile'}
                                    </button>
                                </div>
                                {multisport ? (
                                    <p className="small">
                                        One FIT course for each land leg, named
                                        like{' '}
                                        <b>
                                            {legDeviceName(
                                                name,
                                                swim === null ? 1 : 2,
                                                legs[0]?.sport ?? 'road',
                                            )}
                                        </b>
                                        . The GPX holds every leg as its own
                                        track. The swim has no course.
                                    </p>
                                ) : (
                                    <p className="small">
                                        FIT for Garmin. GPX for Wahoo,
                                        Hammerhead, Bryton and COROS. Device
                                        name{' '}
                                        <b>
                                            {(name.trim() || 'Traceway').slice(
                                                0,
                                                15,
                                            )}
                                        </b>
                                        ,{' '}
                                        {Math.min(
                                            15,
                                            (name.trim() || 'Traceway').length,
                                        )}{' '}
                                        of 15 characters.
                                    </p>
                                )}
                            </section>
                        </div>
                    </aside>
                </main>
            </div>

            <Sheet
                open={sheet === 'sport'}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent
                    side="bottom"
                    className="tw-pop top-auto block h-fit max-h-[80vh] gap-0 overflow-y-auto rounded-t-[10px] bg-background pb-4 text-foreground shadow-sm"
                >
                    <SheetHeader className={sheetHeading}>
                        <SheetTitle className="pr-6">Choose a sport</SheetTitle>
                    </SheetHeader>
                    <div className="px-4 pb-3">
                        <input
                            value={sportQuery}
                            onChange={(event) =>
                                setSportQuery(event.target.value)
                            }
                            placeholder="Search"
                            aria-label="Search"
                            className="h-8 w-full rounded-[10px] border border-border bg-background px-3 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <OptionGroup
                        title="Foot"
                        options={visibleSports.filter(
                            (item) => item.group === 'Foot',
                        )}
                        selected={sheetSport}
                        onSelect={chooseSport}
                    />
                    <OptionGroup
                        title="Bike"
                        options={visibleSports.filter(
                            (item) => item.group === 'Bike',
                        )}
                        selected={sheetSport}
                        onSelect={chooseSport}
                    />
                </SheetContent>
            </Sheet>

            <Sheet
                open={sheet === 'length'}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent
                    side="bottom"
                    className={choiceSheet}
                    style={{ maxHeight: '24rem' }}
                    onOpenAutoFocus={(event) => {
                        // A selected number makes Firefox repeat the first
                        // key, and Backspace deletes the whole field.
                        event.preventDefault();
                    }}
                >
                    <SheetHeader className={sheetHeading}>
                        <SheetTitle className="pr-6">Length</SheetTitle>
                    </SheetHeader>
                    {sheet === 'length' ? (
                        <LengthEditor
                            initialKm={sheetKm}
                            onApply={(kilometres) => {
                                if (multisport) {
                                    updateLeg({ distanceKm: kilometres });

                                    return;
                                }

                                setDistanceKm(kilometres);
                                setSheet(null);
                                redraw({ distanceKm: kilometres });
                            }}
                        />
                    ) : null}
                </SheetContent>
            </Sheet>

            <Sheet
                open={sheet === 'roads'}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent side="bottom" className={choiceSheet}>
                    <SheetHeader className={sheetHeading}>
                        <SheetTitle className="pr-6">Roads</SheetTitle>
                    </SheetHeader>
                    <ChoiceList label="Roads">
                        {roadsOptions.map((option) => (
                            <RadioRow
                                key={option.value}
                                label={option.label}
                                copy={option.copy}
                                selected={sheetRoads === option.value}
                                onSelect={() => {
                                    if (multisport) {
                                        updateLeg({ roads: option.value });

                                        return;
                                    }

                                    setRoads(option.value);
                                    setSheet(null);

                                    if (mode === 'build') {
                                        if (buildPoints.length > 0) {
                                            void drawBuild({
                                                roads: option.value,
                                            });
                                        }

                                        return;
                                    }

                                    if (routes.length > 0) {
                                        void generate({ roads: option.value });
                                    }
                                }}
                            />
                        ))}
                    </ChoiceList>
                </SheetContent>
            </Sheet>

            <Sheet
                open={sheet === 'elevation'}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent
                    side="bottom"
                    className={choiceSheet}
                    style={{ maxHeight: '24rem' }}
                >
                    <SheetHeader className={sheetHeading}>
                        <SheetTitle className="pr-6">Elevation</SheetTitle>
                    </SheetHeader>
                    <ChoiceList label="Elevation">
                        {elevations.map((option) => (
                            <RadioRow
                                key={option.value}
                                label={option.label}
                                copy={option.copy}
                                selected={sheetElevation === option.value}
                                onSelect={() => chooseElevation(option.value)}
                            />
                        ))}
                    </ChoiceList>
                </SheetContent>
            </Sheet>

            <Sheet
                open={sheet === 'shape'}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent
                    side="bottom"
                    className={choiceSheet}
                    style={{ maxHeight: '24rem' }}
                >
                    <SheetHeader className={sheetHeading}>
                        <SheetTitle className="pr-6">Shape</SheetTitle>
                    </SheetHeader>
                    <ChoiceList label="Route shape">
                        {shapes.map((option) => (
                            <RadioRow
                                key={option.value}
                                label={option.label}
                                copy={option.copy}
                                selected={shape === option.value}
                                onSelect={() => chooseShape(option.value)}
                            />
                        ))}
                    </ChoiceList>
                </SheetContent>
            </Sheet>

            <Sheet
                open={sheet === 'map'}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent
                    side="bottom"
                    className={choiceSheet}
                    style={{ maxHeight: '24rem' }}
                >
                    <SheetHeader className={sheetHeading}>
                        <SheetTitle className="pr-6">Map</SheetTitle>
                    </SheetHeader>
                    <ChoiceList label="Map style">
                        {mapStyles.map((option) => (
                            <RadioRow
                                key={option.id}
                                label={option.label}
                                copy={option.copy}
                                selected={mapStyle === option.id}
                                onSelect={() => chooseMap(option.id)}
                            />
                        ))}
                    </ChoiceList>
                </SheetContent>
            </Sheet>

            <Sheet
                open={sheet === 'surface'}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent
                    side="bottom"
                    className={choiceSheet}
                    style={{ maxHeight: '24rem' }}
                >
                    <SheetHeader className={sheetHeading}>
                        <SheetTitle className="pr-6">Surface</SheetTitle>
                    </SheetHeader>
                    <ChoiceList label="Surface">
                        {surfaces.map((option) => (
                            <RadioRow
                                key={option.value}
                                label={option.label}
                                copy={option.copy}
                                selected={sheetSurface === option.value}
                                onSelect={() => chooseSurface(option.value)}
                            />
                        ))}
                    </ChoiceList>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ChoiceList({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="choices" role="radiogroup" aria-label={label}>
            {children}
        </div>
    );
}

function OptionGroup({
    title,
    options,
    selected,
    onSelect,
}: {
    title: string;
    options: SportOption[];
    selected: string;
    onSelect: (value: string) => void;
}) {
    if (options.length === 0) {
        return null;
    }

    return (
        <div className="px-4 pb-4">
            <h3 className="group-label">{title}</h3>
            <div className="choices">
                {options.map((option) => {
                    const active = selected === option.value;

                    return (
                        <button
                            key={option.value}
                            type="button"
                            className={cn('choice', active && 'on')}
                            onClick={() => onSelect(option.value)}
                        >
                            <span className="t">
                                <b>{option.label}</b>
                                <small>{option.copy}</small>
                            </span>
                            <span className="check" aria-hidden="true">
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M3 8.5 L6.5 12 L13 4.5"
                                        stroke="oklch(0.2 0.04 200)"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function RadioRow({
    label,
    copy,
    selected,
    onSelect,
}: {
    label: string;
    copy: string;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            className={cn('choice', selected && 'on')}
        >
            <span className="t">
                <b>{label}</b>
                <small>{copy}</small>
            </span>
            <span className="check" aria-hidden="true">
                <svg
                    width="12"
                    height="12"
                    viewBox="0 0 16 16"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M3 8.5 L6.5 12 L13 4.5"
                        stroke="oklch(0.2 0.04 200)"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </span>
        </button>
    );
}
