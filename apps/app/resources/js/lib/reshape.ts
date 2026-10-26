import { metresToLine } from '@/lib/avoided-roads';
import { csrfToken } from '@/lib/places';
import type { Coordinate } from '@/lib/route-arrows';
import { metresBetween } from '@/lib/route-arrows';

/** One change to a drawn route, as the reshape endpoint takes it. */
export type RouteChange =
    | { kind: 'move'; index: number; point: Coordinate }
    | { kind: 'insert'; index: number; point: Coordinate }
    | { kind: 'remove'; index: number }
    | { kind: 'reroute'; legs: number[] };

export type ReshapeAsk = {
    mode: 'loop' | 'build';
    /** A built route whose last leg is the way home Traceway found. */
    finish?: boolean;
    line: Coordinate[];
    waypoints: Coordinate[];
    change: RouteChange;
    sport: string;
    surface: string;
    elevation: string;
    roads: string;
    distanceMetres?: number;
};

export type Reshaped = {
    distance_metres: number;
    ascent_metres: number;
    coordinates: Coordinate[];
    waypoints?: Coordinate[];
    user_coordinates?: Coordinate[];
    finish_coordinates?: Coordinate[];
    user_metres?: number;
    finish_metres?: number;
    legs_metres?: number[];
    changed_legs?: number[];
    reverse_share?: number;
    doubled_metres?: number;
    crosses_avoided?: string[];
};

/** The most legs one change plans again; more, and the route is drawn again whole. */
export const mostLegs = 3;

/**
 * Plans again only the legs a change touches, and keeps the rest of the
 * line. Null with a message when the server could not.
 */
export async function reshapeRoute(
    ask: ReshapeAsk,
): Promise<{ route: Reshaped } | { message: string }> {
    const { change } = ask;

    try {
        const response = await fetch('/routes/reshape', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                sport: ask.sport,
                surface: ask.surface,
                elevation: ask.elevation,
                roads: ask.roads,
                mode: ask.mode,
                finish: ask.finish ?? false,
                ...(ask.distanceMetres === undefined
                    ? {}
                    : { distance_metres: ask.distanceMetres }),
                line: ask.line,
                waypoints: ask.waypoints,
                change:
                    change.kind === 'reroute'
                        ? { kind: 'reroute', legs: change.legs }
                        : change.kind === 'remove'
                          ? { kind: 'remove', index: change.index }
                          : {
                                kind: change.kind,
                                index: change.index,
                                latitude: change.point[1],
                                longitude: change.point[0],
                            },
            }),
        });
        const body: unknown = await response.json().catch(() => null);

        if (
            response.ok &&
            typeof body === 'object' &&
            body !== null &&
            'coordinates' in body &&
            Array.isArray(body.coordinates) &&
            'distance_metres' in body &&
            typeof body.distance_metres === 'number'
        ) {
            return { route: body as Reshaped };
        }

        return {
            message:
                typeof body === 'object' &&
                body !== null &&
                'message' in body &&
                typeof body.message === 'string'
                    ? body.message
                    : 'The roads around that point did not join up.',
        };
    } catch {
        return { message: 'The map is busy. Try again in a moment.' };
    }
}

/**
 * Where each waypoint falls on the line, in order, never going back: the
 * first close pass, as the server cuts it.
 */
export function cutsOf(line: Coordinate[], waypoints: Coordinate[]): number[] {
    const last = line.length - 1;
    const cuts: number[] = [];
    let from = 0;

    waypoints.forEach((waypoint, position) => {
        if (position === 0) {
            cuts.push(0);

            return;
        }

        if (position === waypoints.length - 1) {
            cuts.push(last);

            return;
        }

        const distances = line
            .slice(from)
            .map((vertex) => metresBetween(waypoint, vertex));
        const closest = Math.min(...distances);
        const near = closest + Math.max(15, closest * 0.25);
        let cut = from;
        let pass: number | null = null;

        // The first pass that comes about as near as the nearest one, at its
        // nearest vertex, as the server cuts it: a loop's later pass loses.
        for (let index = 0; index < distances.length; index += 1) {
            const metres = distances[index] ?? Number.POSITIVE_INFINITY;

            if (metres > closest + 30) {
                if (pass !== null && (distances[pass] ?? 0) <= near) {
                    break;
                }

                pass = null;

                continue;
            }

            if (
                pass === null ||
                metres < (distances[pass] ?? Number.POSITIVE_INFINITY)
            ) {
                pass = index;
            }

            cut = from + pass;
        }

        cuts.push(cut);
        from = cut;
    });

    return cuts;
}

/** The legs of a line that pass within reach of a road. */
export function legsNear(
    line: Coordinate[],
    waypoints: Coordinate[],
    road: Coordinate[],
    withinMetres = 30,
): number[] {
    const cuts = cutsOf(line, waypoints);
    const legs: number[] = [];

    for (let leg = 1; leg < cuts.length; leg += 1) {
        const piece = line.slice(cuts[leg - 1], (cuts[leg] ?? 0) + 1);

        if (
            piece.some((vertex) => metresToLine(vertex, road) <= withinMetres)
        ) {
            legs.push(leg - 1);
        }
    }

    return legs;
}

/** The waypoint between the start and the finish that lies nearest a point. */
export function nearestStop(
    waypoints: Coordinate[],
    point: Coordinate,
): number | null {
    let best: number | null = null;
    let bestMetres = Number.POSITIVE_INFINITY;

    for (let index = 1; index < waypoints.length - 1; index += 1) {
        const stop = waypoints[index];

        if (stop === undefined) {
            continue;
        }

        const metres = metresBetween(stop, point);

        if (metres < bestMetres) {
            bestMetres = metres;
            best = index;
        }
    }

    return best;
}
