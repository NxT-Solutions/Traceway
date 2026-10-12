import type { Coordinate } from '@/lib/route-arrows';

/**
 * A brick, a duathlon, or a triathlon. The routing engine draws every land
 * leg. The swim is a pin or a line the athlete draws, because the engine
 * does not route on water.
 */
export type Layout = 'loops' | 'chain';

export type LandLeg = {
    id: string;
    sport: string;
    distanceKm: number;
    surface: string;
    elevation: string;
    roads: 'quiet' | 'any';
    finish: Coordinate | null;
};

export type PlannedLeg = {
    discipline: string;
    distance_metres: number;
    ascent_metres: number;
    coordinates: Coordinate[];
};

export type MultisportPlan = {
    layout: Layout;
    distance_metres: number;
    ascent_metres: number;
    legs: PlannedLeg[];
};

export const mostLandLegs = 4;
export const mostSwimPoints = 20;

export const layouts = [
    {
        value: 'loops',
        label: 'Loops from T',
        copy: 'Every leg leaves the transition and comes back to it.',
    },
    {
        value: 'chain',
        label: 'Chain',
        copy: 'Each leg ends where the next one starts.',
    },
] as const;

function leg(
    sport: string,
    distanceKm: number,
    surface = 'paved',
): Omit<LandLeg, 'id'> {
    return {
        sport,
        distanceKm,
        surface,
        elevation: 'neutral',
        roads: 'quiet',
        finish: null,
    };
}

export const presets = [
    {
        value: 'brick',
        label: 'Brick',
        copy: 'Ride, then run straight off the bike.',
        swim: false,
        legs: [leg('road', 40), leg('run', 5)],
    },
    {
        value: 'triathlon',
        label: 'Triathlon',
        copy: 'Swim, ride, run. Olympic distances.',
        swim: true,
        legs: [leg('road', 40), leg('run', 10)],
    },
    {
        value: 'duathlon',
        label: 'Duathlon',
        copy: 'Run, ride, run again.',
        swim: false,
        legs: [leg('run', 10), leg('road', 40), leg('run', 5)],
    },
] as const;

export function withIds(legs: ReadonlyArray<Omit<LandLeg, 'id'>>): LandLeg[] {
    return legs.map((item) => ({
        ...item,
        finish: item.finish === null ? null : [...item.finish],
        id: crypto.randomUUID(),
    }));
}

export function newLeg(sport: string): LandLeg {
    return { ...leg(sport, sport === 'run' ? 5 : 20), id: crypto.randomUUID() };
}

const landPaint = { line: '#16303a', label: 'Bike' } as const;
const footPaint = { line: '#6d28d9', label: 'Foot' } as const;
const swimPaint = { line: '#1d4ed8', label: 'Swim' } as const;

/**
 * Bikes in ink, foot in violet, the swim in blue and dashed. Colours match
 * the planner route palette, which the map needs as literal values.
 */
export function legPaint(discipline: string): { line: string; label: string } {
    if (discipline === 'swim') {
        return swimPaint;
    }

    return ['road', 'gravel', 'mtb'].includes(discipline)
        ? landPaint
        : footPaint;
}

const deviceLabels: Record<string, string> = {
    road: 'Road',
    gravel: 'Gravel',
    mtb: 'MTB',
    run: 'Run',
    trail_run: 'Trail',
    walk: 'Walk',
    hike: 'Hike',
    swim: 'Swim',
};

/**
 * The name a watch shows for one leg, such as "Brick 2 Run". The server
 * writes the same name into the FIT course.
 */
export function legDeviceName(
    base: string,
    position: number,
    discipline: string,
): string {
    const suffix = ` ${position} ${deviceLabels[discipline] ?? 'Leg'}`;
    const trimmed = base.trim() === '' ? 'Leg' : base.trim();
    const room = Math.max(1, 15 - [...suffix].length);

    return `${[...trimmed].slice(0, room).join('').trimEnd()}${suffix}`;
}

export function joinedLine(plan: MultisportPlan): Coordinate[] {
    return plan.legs.flatMap((item) =>
        item.coordinates.length > 1 ? item.coordinates : [],
    );
}

function xml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&apos;');
}

/**
 * One track per leg. A swim pin is a waypoint.
 */
export function multisportGpx(name: string, plan: MultisportPlan): string {
    const base = name.trim() === '' ? 'Traceway' : name.trim();
    let waypoints = '';
    let tracks = '';

    plan.legs.forEach((item, index) => {
        const label = xml(
            item.discipline === 'swim'
                ? 'Swim'
                : legDeviceName(base, index + 1, item.discipline),
        );
        const type = xml(item.discipline);

        if (item.coordinates.length < 2) {
            waypoints += item.coordinates
                .map(
                    ([longitude, latitude]) =>
                        `<wpt lat="${latitude}" lon="${longitude}"><name>${label}</name><type>${type}</type></wpt>`,
                )
                .join('');

            return;
        }

        const points = item.coordinates
            .map(
                ([longitude, latitude]) =>
                    `<trkpt lat="${latitude}" lon="${longitude}"></trkpt>`,
            )
            .join('');
        tracks += `<trk><name>${label}</name><type>${type}</type><trkseg>${points}</trkseg></trk>`;
    });

    return `<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" creator="Traceway"><metadata><name>${xml(base)}</name></metadata>${waypoints}${tracks}</gpx>`;
}

export function isCoordinate(value: unknown): value is Coordinate {
    return (
        Array.isArray(value) &&
        value.length === 2 &&
        typeof value[0] === 'number' &&
        typeof value[1] === 'number'
    );
}

export function isPlan(value: unknown): value is MultisportPlan {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        (record.layout === 'loops' || record.layout === 'chain') &&
        typeof record.distance_metres === 'number' &&
        typeof record.ascent_metres === 'number' &&
        Array.isArray(record.legs) &&
        record.legs.every((item: unknown) => {
            if (typeof item !== 'object' || item === null) {
                return false;
            }

            const legRecord = item as Record<string, unknown>;

            return (
                typeof legRecord.discipline === 'string' &&
                typeof legRecord.distance_metres === 'number' &&
                typeof legRecord.ascent_metres === 'number' &&
                Array.isArray(legRecord.coordinates) &&
                legRecord.coordinates.every(isCoordinate)
            );
        })
    );
}

export function isLandLeg(value: unknown): value is LandLeg {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        typeof record.id === 'string' &&
        typeof record.sport === 'string' &&
        typeof record.distanceKm === 'number' &&
        typeof record.surface === 'string' &&
        typeof record.elevation === 'string' &&
        (record.roads === 'quiet' || record.roads === 'any') &&
        (record.finish === null || isCoordinate(record.finish))
    );
}
