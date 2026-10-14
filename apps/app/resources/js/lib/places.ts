/**
 * Places for the planner. Every lookup goes through Traceway, so the rider's
 * browser never sends a pin or a search to the place search itself.
 */

export type PlaceKind = 'address' | 'street' | 'town' | 'place';

export type FoundPlace = {
    name: string;
    area: string;
    latitude: number;
    longitude: number;
    kind?: PlaceKind;
};

export type PlaceSearchResult =
    | { kind: 'found'; places: FoundPlace[] }
    | { kind: 'busy'; message: string };

const mostRecent = 5;

export function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    const token = match?.[1];

    return token === undefined ? '' : decodeURIComponent(token);
}

function postJson(url: string, body: unknown): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
}

/**
 * Names the village, town, or quarter around a point. "Junction" when the
 * map has no name for it or the place search did not answer.
 */
export async function labelPlace(
    longitude: number,
    latitude: number,
): Promise<string> {
    try {
        const response = await postJson('/routes/place', {
            latitude,
            longitude,
        });
        const body: unknown = await response.json();

        if (
            typeof body === 'object' &&
            body !== null &&
            'name' in body &&
            typeof body.name === 'string' &&
            body.name !== ''
        ) {
            return body.name;
        }
    } catch {
        return 'Junction';
    }

    return 'Junction';
}

function isFoundPlace(value: unknown): value is FoundPlace {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        typeof record.name === 'string' &&
        typeof record.area === 'string' &&
        typeof record.latitude === 'number' &&
        typeof record.longitude === 'number' &&
        (record.kind === undefined ||
            record.kind === 'address' ||
            record.kind === 'street' ||
            record.kind === 'town' ||
            record.kind === 'place')
    );
}

/** The browser's language, as the place search takes it: "nl" or "en-GB". */
function browserLanguage(): string | undefined {
    const language = typeof navigator === 'undefined' ? '' : navigator.language;
    const match = language.match(/^([a-z]{2})(?:-([a-z]{2}))?/i);

    if (match === null) {
        return undefined;
    }

    return match[2] === undefined
        ? (match[1] ?? '').toLowerCase()
        : `${(match[1] ?? '').toLowerCase()}-${match[2].toUpperCase()}`;
}

/**
 * A pasted "51.12, 4.90" is a place already, so it never leaves the page.
 * The same rule as the AI tools' start point.
 */
export function parseCoordinates(text: string): FoundPlace | null {
    const match = text
        .trim()
        .match(/^(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/);

    if (match === null) {
        return null;
    }

    const latitude = Number(match[1]);
    const longitude = Number(match[2]);

    if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
        return null;
    }

    return {
        name: `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`,
        area: 'Coordinates',
        latitude,
        longitude,
    };
}

/**
 * Finds places by name, the ones near the map first.
 */
export async function searchPlaces(
    query: string,
    near: { latitude: number; longitude: number },
): Promise<PlaceSearchResult> {
    const busy: PlaceSearchResult = {
        kind: 'busy',
        message: 'Search is busy. Try again in a moment.',
    };
    const typed = parseCoordinates(query);

    if (typed !== null) {
        return { kind: 'found', places: [typed] };
    }

    try {
        const response = await postJson('/routes/places', {
            query,
            latitude: near.latitude,
            longitude: near.longitude,
            language: browserLanguage(),
        });
        const body: unknown = await response.json();

        if (!response.ok) {
            const message =
                typeof body === 'object' &&
                body !== null &&
                'message' in body &&
                typeof body.message === 'string'
                    ? body.message
                    : busy.message;

            return { kind: 'busy', message };
        }

        const places =
            typeof body === 'object' &&
            body !== null &&
            'places' in body &&
            Array.isArray(body.places)
                ? body.places.filter(isFoundPlace)
                : [];

        return { kind: 'found', places };
    } catch {
        return busy;
    }
}

/**
 * The last few places the rider picked, newest first. Kept in this browser
 * only, and empty when the browser keeps nothing.
 */
export function recentPlaces(storageKey: string): FoundPlace[] {
    try {
        const raw = window.localStorage.getItem(storageKey);
        const value: unknown = raw === null ? [] : JSON.parse(raw);

        return Array.isArray(value)
            ? value.filter(isFoundPlace).slice(0, mostRecent)
            : [];
    } catch {
        return [];
    }
}

export function rememberPlace(storageKey: string, place: FoundPlace): void {
    try {
        const others = recentPlaces(storageKey).filter(
            (item) =>
                item.name !== place.name ||
                item.latitude !== place.latitude ||
                item.longitude !== place.longitude,
        );
        window.localStorage.setItem(
            storageKey,
            JSON.stringify([place, ...others].slice(0, mostRecent)),
        );
    } catch {
        // A browser that keeps nothing still searches.
    }
}
