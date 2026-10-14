import type { Coordinate } from '@/lib/route-arrows';
import {
    gpxStopWaypoints,
    gpxTrackPoints,
    type RouteDetails,
} from '@/lib/route-details';

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
    /**
     * A colour the athlete picked for this leg. Without one, the leg takes
     * the colour for its place in the chosen colour set.
     */
    color?: string | null;
    /**
     * In a chain, the leg covers distanceKm on the way to its finish
     * instead of taking the shortest way there.
     */
    stretch?: boolean;
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
        copy: 'Swim, ride, run.',
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
    {
        value: 'aquabike',
        label: 'Aquabike',
        copy: 'Swim, then ride. No run.',
        swim: true,
        legs: [leg('road', 40)],
    },
] as const;

export type SessionKind = (typeof presets)[number]['value'] | 'custom';

export const customSession = {
    value: 'custom',
    label: 'Custom',
    copy: 'Your own mix of legs. Pick a session to start from one.',
} as const;

const bikeSports = ['road', 'gravel', 'mtb'];
const runSports = ['run', 'trail_run'];

/**
 * The session the legs add up to. A brick is a ride then a run, a
 * triathlon is the same after a swim, and a duathlon is a run, a ride, and
 * a run. An aquabike is a swim and a ride. Any bike sport counts as the
 * ride and a run or trail run as the run. Anything else is custom.
 */
export function sessionOf(legs: LandLeg[], hasSwim: boolean): SessionKind {
    const shape = legs
        .map((item) =>
            bikeSports.includes(item.sport)
                ? 'bike'
                : runSports.includes(item.sport)
                  ? 'run'
                  : 'other',
        )
        .join(' ');

    if (shape === 'bike run') {
        return hasSwim ? 'triathlon' : 'brick';
    }

    if (shape === 'run bike run' && !hasSwim) {
        return 'duathlon';
    }

    if (shape === 'bike' && hasSwim) {
        return 'aquabike';
    }

    return 'custom';
}

/**
 * The official and best-known race distances, so a session can rehearse a
 * whole race. Leg lengths follow the leg order of the session. The quarter
 * and eighth are Benelux formats whose distances vary by race, so they take
 * the most common ones. A brick has no race of its own, so it takes the
 * ride and run of each triathlon.
 */
export const raceDistances = {
    triathlon: [
        {
            value: 'super_sprint',
            label: 'Super sprint',
            name: 'Super sprint triathlon',
            copy: '400 m swim, 10 km ride, 2.5 km run.',
            swimKm: 0.4,
            legs: [10, 2.5],
        },
        {
            value: 'eighth',
            label: 'Eighth (1/8)',
            name: 'Eighth triathlon',
            copy: '500 m swim, 20 km ride, 5 km run. The short Benelux format.',
            swimKm: 0.5,
            legs: [20, 5],
        },
        {
            value: 'sprint',
            label: 'Sprint',
            name: 'Sprint triathlon',
            copy: '750 m swim, 20 km ride, 5 km run.',
            swimKm: 0.75,
            legs: [20, 5],
        },
        {
            value: 'quarter',
            label: 'Quarter (1/4)',
            name: 'Quarter triathlon',
            copy: '1 km swim, 42 km ride, 10 km run. Races ride 40 to 45 km.',
            swimKm: 1,
            legs: [42, 10],
        },
        {
            value: 'olympic',
            label: 'Olympic',
            name: 'Olympic triathlon',
            copy: 'Standard distance: 1.5 km swim, 40 km ride, 10 km run.',
            swimKm: 1.5,
            legs: [40, 10],
        },
        {
            value: 't100',
            label: 'T100',
            name: 'T100',
            copy: '2 km swim, 80 km ride, 18 km run.',
            swimKm: 2,
            legs: [80, 18],
        },
        {
            value: 'ironman_70_3',
            label: 'Ironman 70.3',
            name: 'Ironman 70.3',
            copy: 'Half distance: 1.9 km swim, 90 km ride, 21.1 km run.',
            swimKm: 1.9,
            legs: [90, 21.1],
        },
        {
            value: 'ironman',
            label: 'Ironman',
            name: 'Ironman',
            copy: 'Full distance: 3.8 km swim, 180 km ride, 42.2 km run.',
            swimKm: 3.8,
            legs: [180, 42.2],
        },
    ],
    duathlon: [
        {
            value: 'sprint',
            label: 'Sprint',
            name: 'Sprint duathlon',
            copy: '5 km run, 20 km ride, 2.5 km run.',
            swimKm: null,
            legs: [5, 20, 2.5],
        },
        {
            value: 'standard',
            label: 'Standard',
            name: 'Standard duathlon',
            copy: '10 km run, 40 km ride, 5 km run.',
            swimKm: null,
            legs: [10, 40, 5],
        },
        {
            value: 'powerman',
            label: 'Powerman',
            name: 'Powerman',
            copy: 'Long distance: 10 km run, 150 km ride, 30 km run.',
            swimKm: null,
            legs: [10, 150, 30],
        },
    ],
    brick: [
        {
            value: 'sprint',
            label: 'Sprint',
            name: 'Sprint brick',
            copy: 'The ride and run of a sprint triathlon: 20 km, then 5 km.',
            swimKm: null,
            legs: [20, 5],
        },
        {
            value: 'olympic',
            label: 'Olympic',
            name: 'Olympic brick',
            copy: 'The ride and run of an Olympic triathlon: 40 km, then 10 km.',
            swimKm: null,
            legs: [40, 10],
        },
        {
            value: 't100',
            label: 'T100',
            name: 'T100 brick',
            copy: 'The ride and run of a T100: 80 km, then 18 km.',
            swimKm: null,
            legs: [80, 18],
        },
        {
            value: 'ironman_70_3',
            label: 'Ironman 70.3',
            name: 'Ironman 70.3 brick',
            copy: 'The ride and run of an Ironman 70.3: 90 km, then 21.1 km.',
            swimKm: null,
            legs: [90, 21.1],
        },
        {
            value: 'ironman',
            label: 'Ironman',
            name: 'Ironman brick',
            copy: 'The ride and run of an Ironman: 180 km, then 42.2 km.',
            swimKm: null,
            legs: [180, 42.2],
        },
    ],
    aquabike: [
        {
            value: 'standard',
            label: 'Standard',
            name: 'Standard aquabike',
            copy: '1.5 km swim, 40 km ride.',
            swimKm: 1.5,
            legs: [40],
        },
        {
            value: 'middle',
            label: 'Middle',
            name: 'Middle aquabike',
            copy: 'The swim and ride of an Ironman 70.3: 1.9 km, then 90 km.',
            swimKm: 1.9,
            legs: [90],
        },
        {
            value: 'long',
            label: 'Long',
            name: 'Long aquabike',
            copy: 'The swim and ride of an Ironman: 3.8 km, then 180 km.',
            swimKm: 3.8,
            legs: [180],
        },
    ],
} as const;

export type RaceDistance = {
    value: string;
    label: string;
    /** What the plan is called once the race is chosen. */
    name: string;
    copy: string;
    swimKm: number | null;
    legs: readonly number[];
};

export function raceDistancesFor(
    session: SessionKind,
): readonly RaceDistance[] {
    return session === 'custom' ? [] : raceDistances[session];
}

/**
 * The race whose distances the legs match, if any.
 */
export function raceOf(
    session: SessionKind,
    legs: LandLeg[],
    swimTargetKm: number | null,
): RaceDistance | null {
    const near = (left: number, right: number) => Math.abs(left - right) < 0.05;

    return (
        raceDistancesFor(session).find(
            (race) =>
                race.legs.length === legs.length &&
                race.legs.every((km, index) =>
                    near(km, legs[index]?.distanceKm ?? -1),
                ) &&
                (race.swimKm === null ||
                    (swimTargetKm !== null && near(race.swimKm, swimTargetKm))),
        ) ?? null
    );
}

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

export type Colour = { hex: string; name: string };

/**
 * Sets of five colours, one for each leg a plan can hold. The colours in a
 * set differ in hue and in lightness, so two legs on the same road stay
 * apart, and none of them is the yellow or orange the basemap uses for
 * roads. Leg 1 takes the first colour, leg 2 the second, and so on.
 */
export const colourSets = [
    {
        value: 'bold',
        label: 'Bold',
        copy: 'Strong colours for the streets and light maps.',
        colours: [
            { hex: '#2563eb', name: 'Blue' },
            { hex: '#db2777', name: 'Magenta' },
            { hex: '#15803d', name: 'Green' },
            { hex: '#7c3aed', name: 'Violet' },
            { hex: '#111827', name: 'Ink' },
        ],
    },
    {
        value: 'colour_blind',
        label: 'Colour-blind safe',
        copy: 'Okabe and Ito colours that stay apart with every kind of colour blindness.',
        colours: [
            { hex: '#0072b2', name: 'Blue' },
            { hex: '#cc79a7', name: 'Reddish purple' },
            { hex: '#009e73', name: 'Bluish green' },
            { hex: '#000000', name: 'Black' },
            { hex: '#56b4e9', name: 'Sky blue' },
        ],
    },
    {
        value: 'night',
        label: 'Night',
        copy: 'Bright colours that read on the dark map.',
        colours: [
            { hex: '#38bdf8', name: 'Sky' },
            { hex: '#f472b6', name: 'Pink' },
            { hex: '#4ade80', name: 'Mint' },
            { hex: '#c4b5fd', name: 'Lilac' },
            { hex: '#f8fafc', name: 'White' },
        ],
    },
    {
        value: 'earth',
        label: 'Earth',
        copy: 'Deep, quieter colours for a busy map.',
        colours: [
            { hex: '#1e3a8a', name: 'Navy' },
            { hex: '#9f1239', name: 'Wine' },
            { hex: '#166534', name: 'Forest' },
            { hex: '#6b21a8', name: 'Plum' },
            { hex: '#374151', name: 'Slate' },
        ],
    },
] as const;

export type ColourSet = (typeof colourSets)[number]['value'];

export function isColourSet(value: unknown): value is ColourSet {
    return colourSets.some((set) => set.value === value);
}

export function coloursOf(set: ColourSet): readonly Colour[] {
    return (colourSets.find((item) => item.value === set) ?? colourSets[0])
        .colours;
}

/**
 * The colour for the leg at this place in the plan, counting the swim.
 */
export function legColour(
    set: ColourSet,
    position: number,
    picked?: string | null,
): string {
    if (typeof picked === 'string' && isHexColour(picked)) {
        return picked;
    }

    const colours = coloursOf(set);

    return colours[position % colours.length]?.hex ?? '#2563eb';
}

function luminance(hex: string): number {
    const channels = [1, 3, 5].map((start) => {
        const value = Number.parseInt(hex.slice(start, start + 2), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return (
        0.2126 * (channels[0] ?? 0) +
        0.7152 * (channels[1] ?? 0) +
        0.0722 * (channels[2] ?? 0)
    );
}

/**
 * White on a dark leg colour, ink on a light one, so a number on the colour
 * keeps the 4.5:1 contrast WCAG asks of text.
 */
export function inkOn(hex: string): string {
    if (!isHexColour(hex)) {
        return '#ffffff';
    }

    const background = luminance(hex);

    return 1.05 / (background + 0.05) >= 4.5 ? '#ffffff' : '#0f172a';
}

export function colourName(hex: string): string {
    for (const set of colourSets) {
        const found = set.colours.find(
            (colour) => colour.hex === hex.toLowerCase(),
        );

        if (found !== undefined) {
            return found.name;
        }
    }

    return hex;
}

export function isHexColour(value: unknown): value is string {
    return typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value);
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
 * One track per leg. A swim pin is a waypoint. With details for a leg, its
 * points have heights and its stops are waypoints.
 */
export function multisportGpx(
    name: string,
    plan: MultisportPlan,
    details: (RouteDetails | null)[] = [],
): string {
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

        const legDetails = details[index] ?? null;
        waypoints += gpxStopWaypoints(legDetails);
        tracks += `<trk><name>${label}</name><type>${type}</type><trkseg>${gpxTrackPoints(item.coordinates, legDetails)}</trkseg></trk>`;
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
        (record.finish === null || isCoordinate(record.finish)) &&
        (record.color === undefined ||
            record.color === null ||
            isHexColour(record.color)) &&
        (record.stretch === undefined || typeof record.stretch === 'boolean')
    );
}
