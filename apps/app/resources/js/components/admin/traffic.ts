import type { Ranked } from '@/types/admin';

const regions = new Intl.DisplayNames(['en-GB'], { type: 'region' });

/** A country code from Cloudflare, such as BE, as its name. T1 is Tor, which has no country. */
export function country(code: string): string {
    if (code === 'T1') {
        return 'Tor';
    }

    try {
        return regions.of(code) ?? code;
    } catch {
        return code;
    }
}

export function countries(items: Ranked[]): Ranked[] {
    return items.map((item) => ({
        label: country(item.label),
        count: item.count,
    }));
}

const statusWords: Record<number, string> = {
    200: 'answered',
    204: 'answered, empty',
    206: 'answered in part',
    301: 'redirected',
    302: 'redirected',
    304: 'not changed',
    308: 'redirected',
    401: 'asked to sign in',
    403: 'blocked',
    404: 'not found',
    405: 'wrong method',
    419: 'page expired',
    429: 'too many',
    499: 'gave up',
    500: 'server error',
    502: 'bad gateway',
    503: 'unavailable',
    504: 'timed out',
};

/** An HTTP status with what it means, such as 403 blocked. */
export function status(code: number): string {
    const word = statusWords[code];

    return word ? `${code} ${word}` : String(code);
}

export function share(part: number, whole: number): string {
    return whole === 0 ? '0%' : `${Math.round((part / whole) * 100)}%`;
}

/** The first labels, and the rest added up, so a long list stays short. */
export function top(items: Ranked[], shown: number, rest: string): Ranked[] {
    if (items.length <= shown) {
        return items;
    }

    const others = items.slice(shown - 1);

    return [
        ...items.slice(0, shown - 1),
        {
            label: `${others.length} ${rest}`,
            count: others.reduce((sum, item) => sum + item.count, 0),
        },
    ];
}
