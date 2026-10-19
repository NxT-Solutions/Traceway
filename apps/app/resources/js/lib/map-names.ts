import type * as maplibregl from 'maplibre-gl';
import type { FoundPlace, PlaceKind } from '@/lib/places';

/**
 * Names on the map the rider is looking at: towns, streets, and named spots
 * from the tiles the browser already loaded. They answer while the rider
 * types and forgive a typo, without asking any server.
 */
export type MapName = {
    name: string;
    key: string;
    kind: PlaceKind;
    longitude: number;
    latitude: number;
};

const layers: { layer: string; kind: PlaceKind }[] = [
    { layer: 'place', kind: 'town' },
    { layer: 'transportation_name', kind: 'street' },
    { layer: 'poi', kind: 'place' },
    { layer: 'water_name', kind: 'place' },
    { layer: 'mountain_peak', kind: 'place' },
];

/**
 * Lowercase, without accents or punctuation, in any script: "Sint-Jozef" is
 * "sint jozef", and "Αθήνα" is "αθηνα".
 */
export function normalise(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim();
}

function pointOf(
    geometry: maplibregl.MapGeoJSONFeature['geometry'],
): [number, number] | null {
    if (geometry.type === 'Point') {
        return [geometry.coordinates[0] ?? 0, geometry.coordinates[1] ?? 0];
    }

    const line =
        geometry.type === 'LineString'
            ? geometry.coordinates
            : geometry.type === 'MultiLineString'
              ? geometry.coordinates[0]
              : null;
    const middle = line?.[Math.floor(line.length / 2)];

    return middle === undefined ? null : [middle[0] ?? 0, middle[1] ?? 0];
}

/**
 * Every name in the loaded tiles, once each, at the spot nearest the middle
 * of the map.
 */
export function namesOnMap(map: maplibregl.Map): MapName[] {
    if (!map.isStyleLoaded() || map.getSource('openmaptiles') === undefined) {
        return [];
    }

    const centre = map.getCenter();
    const found = new Map<string, MapName & { distance: number }>();

    for (const { layer, kind } of layers) {
        let features: ReturnType<maplibregl.Map['querySourceFeatures']> = [];

        try {
            features = map.querySourceFeatures('openmaptiles', {
                sourceLayer: layer,
            });
        } catch {
            continue;
        }

        for (const feature of features) {
            const name = feature.properties?.name;
            const point = pointOf(feature.geometry);

            if (typeof name !== 'string' || name === '' || point === null) {
                continue;
            }

            const key = normalise(name);
            const distance = Math.hypot(
                point[0] - centre.lng,
                point[1] - centre.lat,
            );
            const known = found.get(key);

            if (known === undefined || distance < known.distance) {
                found.set(key, {
                    name,
                    key,
                    kind,
                    longitude: point[0],
                    latitude: point[1],
                    distance,
                });
            }
        }
    }

    return [...found.values()]
        .sort((a, b) => a.distance - b.distance)
        .map(({ distance: _distance, ...name }) => name);
}

/** Edits between two words, a swapped pair of letters counting as one. */
export function editDistance(a: string, b: string, limit: number): number {
    if (Math.abs(a.length - b.length) > limit) {
        return limit + 1;
    }

    let before: number[] = [];
    let previous = Array.from({ length: b.length + 1 }, (_, index) => index);

    for (let i = 1; i <= a.length; i += 1) {
        const current = [i];
        let best = i;

        for (let j = 1; j <= b.length; j += 1) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            let value = Math.min(
                (previous[j] ?? 0) + 1,
                (current[j - 1] ?? 0) + 1,
                (previous[j - 1] ?? 0) + cost,
            );

            if (
                i > 1 &&
                j > 1 &&
                a[i - 1] === b[j - 2] &&
                a[i - 2] === b[j - 1]
            ) {
                value = Math.min(value, (before[j - 2] ?? 0) + 1);
            }

            current.push(value);
            best = Math.min(best, value);
        }

        if (best > limit) {
            return limit + 1;
        }

        before = previous;
        previous = current;
    }

    return previous[b.length] ?? limit + 1;
}

/** How many typos a word of this length may have and still match. */
function allowance(length: number): number {
    return length <= 3 ? 0 : length <= 6 ? 1 : length <= 10 ? 2 : 3;
}

/**
 * How far typed words are from a name: 0 for a name that starts with them,
 * then the typos it takes, or null when it is too far.
 */
function closeness(typed: string, name: MapName): number | null {
    if (name.key.startsWith(typed)) {
        return 0;
    }

    if (name.key.includes(typed)) {
        return 0.5;
    }

    const limit = allowance(typed.length);
    const whole = editDistance(typed, name.key, limit);
    // "roodborstln" against the start of a longer name, as the rider types.
    const start = editDistance(typed, name.key.slice(0, typed.length), limit);
    const best = Math.min(whole, start + 0.25);

    return best <= limit ? best : null;
}

/** The words of a search, without house numbers. */
function wordsOf(query: string): string {
    return normalise(query)
        .split(' ')
        .filter((word) => !/^\d+[a-z]?$/.test(word))
        .join(' ');
}

/**
 * Names near the map that look like what the rider typed, the closest
 * spelling first, then the nearest.
 */
export function matchNames(
    query: string,
    names: MapName[],
    limit = 5,
): FoundPlace[] {
    const typed = wordsOf(query);

    if (typed.length < 2) {
        return [];
    }

    const scored: { name: MapName; score: number; order: number }[] = [];
    const words = typed.split(' ');
    // The whole search, then each run of up to three of its words, so
    // "rodborstlaan hobokne" suggests both the street and the town.
    const spans = [typed];

    for (let size = Math.min(3, words.length - 1); size > 0; size -= 1) {
        for (let start = 0; start + size <= words.length; start += 1) {
            const span = words.slice(start, start + size).join(' ');

            if (span.length >= 3) {
                spans.push(span);
            }
        }
    }

    names.forEach((name, order) => {
        let score: number | null = null;

        spans.forEach((span, index) => {
            const close = closeness(span, name);

            if (close !== null) {
                const total = close + (index === 0 ? 0 : 0.5);
                score = score === null ? total : Math.min(score, total);
            }
        });

        if (score !== null) {
            scored.push({ name, score, order });
        }
    });

    return scored
        .sort((a, b) => a.score - b.score || a.order - b.order)
        .slice(0, limit)
        .map(({ name }) => ({
            name: name.name,
            area: 'On the map',
            latitude: name.latitude,
            longitude: name.longitude,
            kind: name.kind,
        }));
}

/**
 * The search with its misspelled words swapped for the names on the map
 * they are closest to, numbers kept: "roodborstln 3 hobokne" becomes
 * "Roodborstlaan 3 Hoboken" when both are near. Null when nothing changed.
 */
export function correctSpelling(
    query: string,
    names: MapName[],
): string | null {
    const words = query
        .trim()
        .split(/[\s,]+/)
        .filter(Boolean);
    const keys = new Set(names.map((name) => name.key));
    const out = [...words];
    let changed = false;

    for (let start = 0; start < words.length; start += 1) {
        for (
            let size = Math.min(3, words.length - start);
            size > 0;
            size -= 1
        ) {
            const span = words.slice(start, start + size);

            if (span.some((word) => /^\d+[a-z]?$/i.test(word))) {
                continue;
            }

            const typed = normalise(span.join(' '));
            const limit = allowance(typed.length);

            if (limit === 0 || keys.has(typed)) {
                continue;
            }

            let best: { name: MapName; distance: number } | null = null;

            for (const name of names) {
                const distance = editDistance(typed, name.key, limit);

                if (
                    distance > 0 &&
                    distance <= limit &&
                    (best === null || distance < best.distance)
                ) {
                    best = { name, distance };
                }
            }

            if (best !== null) {
                out.splice(
                    start,
                    size,
                    best.name.name,
                    ...Array(size - 1).fill(''),
                );
                changed = true;
                start += size - 1;
                break;
            }
        }
    }

    return changed ? out.filter(Boolean).join(' ') : null;
}
