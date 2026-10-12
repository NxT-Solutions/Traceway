// Facts about Traceway that search engines and AI tools read. Keep them true
// to the home page: the same sports, the same devices, the same words.

export const name = 'Traceway';

export const title = 'Traceway · Loop routes for every sport, onto your watch';

export const description =
    'Drop a pin, pick your sport and a distance. Traceway plans a loop from there and back, ready for your watch or bike computer.';

export const sports = [
    'Road cycling',
    'Gravel',
    'Mountain bike',
    'Running',
    'Trail running',
    'Walking and hiking',
];

export const multisport = ['Brick', 'Triathlon', 'Duathlon', 'Aquabike'];

export const devices = ['Garmin', 'COROS', 'Suunto', 'Wahoo', 'Hammerhead'];

export const repository = 'https://github.com/NxT-Solutions/Traceway';

// Planner paths are private. Search engines and AI crawlers stay out, and a
// shared route never shows up in a search, because it shows where someone
// starts.
export const privatePaths = [
    '/dashboard',
    '/saved',
    '/settings',
    '/routes',
    '/share/',
    '/user',
    '/passkeys',
    '/two-factor-challenge',
    '/reset-password',
    '/email',
];

export const absolute = (site: URL | undefined, path: string): string =>
    new URL(path, site ?? 'https://traceway.online').toString();
