import type { FeatureCollection, LineString } from 'geojson';
import { csrfToken } from '@/lib/places';
import type { Coordinate } from '@/lib/route-arrows';

/**
 * A road the rider asked Traceway to skip: one way between two junctions.
 * Every later plan leaves it out, until the rider stops avoiding it.
 */
export type AvoidedRoad = {
    id: string;
    name: string | null;
    latitude: number;
    longitude: number;
    shape: Coordinate[];
};

export type AvoidMiss =
    | { kind: 'far'; message: string }
    | { kind: 'full'; message: string }
    | { kind: 'busy'; message: string }
    | { kind: 'signed-out'; message: string };

/** The line drawn for an avoided road: not the route, not the marker. */
export const avoidedPaint = { line: '#b4232f', casing: '#ffffff' } as const;

export function roadName(road: Pick<AvoidedRoad, 'name'>): string {
    return road.name ?? 'Unnamed road';
}

function isRoad(body: unknown): body is AvoidedRoad {
    return (
        typeof body === 'object' &&
        body !== null &&
        'id' in body &&
        typeof body.id === 'string' &&
        'latitude' in body &&
        typeof body.latitude === 'number' &&
        'longitude' in body &&
        typeof body.longitude === 'number' &&
        'shape' in body &&
        Array.isArray(body.shape)
    );
}

function messageOf(body: unknown, fallback: string): string {
    return typeof body === 'object' &&
        body !== null &&
        'message' in body &&
        typeof body.message === 'string'
        ? body.message
        : fallback;
}

/**
 * Asks Traceway to skip the way under a tapped point, for the sport and
 * surface the route uses, so the way found is one the route could take.
 */
export async function avoidRoad(
    point: Coordinate,
    sport: string,
    surface: string,
): Promise<AvoidedRoad | AvoidMiss> {
    try {
        const response = await fetch('/routes/avoided', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                latitude: point[1],
                longitude: point[0],
                sport,
                surface,
            }),
        });

        if (response.status === 401 || response.status === 419) {
            return {
                kind: 'signed-out',
                message: 'You were signed out. Sign in again to avoid a road.',
            };
        }

        const body: unknown = await response.json().catch(() => null);

        if (response.status === 422) {
            const message = messageOf(
                body,
                'No road or path is close enough there. Tap right on the road.',
            );

            return {
                kind: message.startsWith('You already avoid') ? 'full' : 'far',
                message,
            };
        }

        if (!response.ok || !isRoad(body)) {
            return {
                kind: 'busy',
                message: 'The map is busy. Try again in a moment.',
            };
        }

        return body;
    } catch {
        return {
            kind: 'busy',
            message: 'The map is busy. Try again in a moment.',
        };
    }
}

/** Stops skipping a road. A road already gone counts as done. */
export async function forgetRoad(id: string): Promise<boolean> {
    try {
        const response = await fetch(`/routes/avoided/${id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
        });

        return response.ok || response.status === 404;
    } catch {
        return false;
    }
}

/** The avoided roads as map features, the ones a route still uses marked. */
export function avoidedFeatures(
    roads: AvoidedRoad[],
    crossing: string[] = [],
): FeatureCollection<LineString, { id: string; crossing: boolean }> {
    return {
        type: 'FeatureCollection',
        features: roads
            .filter((road) => road.shape.length > 1)
            .map((road) => ({
                type: 'Feature' as const,
                properties: {
                    id: road.id,
                    crossing: crossing.includes(road.id),
                },
                geometry: {
                    type: 'LineString' as const,
                    coordinates: road.shape,
                },
            })),
    };
}

/** Metres from a point to the nearest part of a line, flat near the point. */
export function metresToLine(point: Coordinate, line: Coordinate[]): number {
    const latScale = 111_320;
    const lonScale = 111_320 * Math.cos((point[1] * Math.PI) / 180);
    let best = Number.POSITIVE_INFINITY;

    for (let index = 1; index < line.length; index += 1) {
        const from = line[index - 1];
        const to = line[index];

        if (from === undefined || to === undefined) {
            continue;
        }

        const ax = (from[0] - point[0]) * lonScale;
        const ay = (from[1] - point[1]) * latScale;
        const bx = (to[0] - point[0]) * lonScale;
        const by = (to[1] - point[1]) * latScale;
        const dx = bx - ax;
        const dy = by - ay;
        const lengthSquared = dx * dx + dy * dy;
        const progress =
            lengthSquared === 0
                ? 0
                : Math.max(
                      0,
                      Math.min(1, -(ax * dx + ay * dy) / lengthSquared),
                  );

        best = Math.min(
            best,
            Math.hypot(ax + progress * dx, ay + progress * dy),
        );
    }

    return best;
}

/** The avoided road a tap landed on, if any. */
export function roadAt(
    point: Coordinate,
    roads: AvoidedRoad[],
    withinMetres = 20,
): AvoidedRoad | null {
    let found: AvoidedRoad | null = null;
    let nearest = withinMetres;

    for (const road of roads) {
        const metres = metresToLine(point, road.shape);

        if (metres <= nearest) {
            nearest = metres;
            found = road;
        }
    }

    return found;
}
