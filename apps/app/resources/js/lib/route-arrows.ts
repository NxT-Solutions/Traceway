import type {
    ExpressionSpecification,
    GeoJSONSource,
    Map as MapLibreMap,
} from 'maplibre-gl';

export type Coordinate = [number, number];

type ArrowCollection = {
    type: 'FeatureCollection';
    features: Array<{
        type: 'Feature';
        geometry: { type: 'Point'; coordinates: Coordinate };
        properties: { bearing: number };
    }>;
};

export function routeBadge(): ImageData {
    const width = 64;
    const height = 88;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');

    if (context === null) {
        throw new Error('Could not draw the direction arrow.');
    }

    const radius = width / 2;
    context.beginPath();
    context.moveTo(radius, 3);
    context.arcTo(width - 3, 3, width - 3, height - 3, radius - 3);
    context.arcTo(width - 3, height - 3, 3, height - 3, radius - 3);
    context.arcTo(3, height - 3, 3, 3, radius - 3);
    context.arcTo(3, 3, width - 3, 3, radius - 3);
    context.closePath();
    context.fillStyle = '#ffffff';
    context.fill();
    context.lineWidth = 6;
    context.strokeStyle = '#16303a';
    context.stroke();

    context.beginPath();
    context.moveTo(width / 2, 16);
    context.lineTo(width / 2 - 14, 38);
    context.lineTo(width / 2 - 5, 38);
    context.lineTo(width / 2 - 5, 68);
    context.lineTo(width / 2 + 5, 68);
    context.lineTo(width / 2 + 5, 38);
    context.lineTo(width / 2 + 14, 38);
    context.closePath();
    context.fillStyle = '#16303a';
    context.fill();

    return context.getImageData(0, 0, width, height);
}

export function metresBetween(from: Coordinate, to: Coordinate): number {
    const lat1 = (from[1] * Math.PI) / 180;
    const lat2 = (to[1] * Math.PI) / 180;
    const dLat = lat2 - lat1;
    const dLon = ((to[0] - from[0]) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;

    return 2 * 6_371_000 * Math.asin(Math.min(1, Math.sqrt(a)));
}

function travelBearing(from: Coordinate, to: Coordinate): number {
    const lat1 = (from[1] * Math.PI) / 180;
    const lat2 = (to[1] * Math.PI) / 180;
    const dLon = ((to[0] - from[0]) * Math.PI) / 180;
    const y = Math.sin(dLon) * Math.cos(lat2);
    const x =
        Math.cos(lat1) * Math.sin(lat2) -
        Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);

    return ((Math.atan2(y, x) * 180) / Math.PI + 360) % 360;
}

function routeLength(coordinates: Coordinate[]): number {
    let total = 0;

    for (let index = 1; index < coordinates.length; index += 1) {
        const previous = coordinates[index - 1];
        const current = coordinates[index];

        if (previous === undefined || current === undefined) {
            continue;
        }

        total += metresBetween(previous, current);
    }

    return total;
}

function arrowScreenScale(): number {
    const span = Math.min(window.innerWidth, window.innerHeight);

    return Math.min(1, Math.max(0.62, span / 900));
}

export function arrowIconSize(): ExpressionSpecification {
    const scale = arrowScreenScale();

    return [
        'interpolate',
        ['linear'],
        ['zoom'],
        10,
        0.48 * scale,
        13,
        0.58 * scale,
        16,
        0.68 * scale,
    ];
}

function arrowSpacing(coordinates: Coordinate[], zoom: number): number {
    const total = routeLength(coordinates);
    const latitude = coordinates[0]?.[1] ?? 51;
    const metresPerPixel =
        (156543.03392 * Math.cos((latitude * Math.PI) / 180)) / 2 ** zoom;
    const onScreen = Math.max(120, metresPerPixel * 72);
    const acrossTheLoop = Math.max(250, total / 16);

    return Math.min(onScreen, acrossTheLoop);
}

export function directionArrows(
    coordinates: Coordinate[],
    zoom: number,
): ArrowCollection {
    const features: ArrowCollection['features'] = [];

    if (coordinates.length < 2) {
        return { type: 'FeatureCollection', features };
    }

    const spacing = arrowSpacing(coordinates, zoom);
    let travelled = 0;
    let nextAt = spacing * 0.55;

    for (let index = 1; index < coordinates.length; index += 1) {
        const previous = coordinates[index - 1];
        const current = coordinates[index];

        if (previous === undefined || current === undefined) {
            continue;
        }

        travelled += metresBetween(previous, current);

        if (travelled < nextAt) {
            continue;
        }

        features.push({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: current },
            properties: { bearing: travelBearing(previous, current) },
        });
        nextAt = travelled + spacing;
    }

    return { type: 'FeatureCollection', features };
}

export function directionArrowsForLines(
    lines: Coordinate[][],
    zoom: number,
): ArrowCollection {
    return {
        type: 'FeatureCollection',
        features: lines.flatMap((line) => directionArrows(line, zoom).features),
    };
}

export function ensureDirectionArrows(
    map: MapLibreMap,
    lines: Coordinate[][],
): void {
    if (!map.hasImage('route-badge')) {
        map.addImage('route-badge', routeBadge(), { pixelRatio: 2 });
    }

    const data = directionArrowsForLines(lines, map.getZoom());
    const source = map.getSource('route-arrows');

    if (source === undefined) {
        map.addSource('route-arrows', { type: 'geojson', data });
    } else if (source.type === 'geojson') {
        (source as GeoJSONSource).setData(data);
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

export function resizeDirectionArrows(map: MapLibreMap): void {
    if (map.getLayer('route-arrows') !== undefined) {
        map.setLayoutProperty('route-arrows', 'icon-size', arrowIconSize());
    }
}
