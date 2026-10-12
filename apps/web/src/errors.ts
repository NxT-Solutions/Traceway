/**
 * Error pages, as words. The planner's Blade error views say the same
 * (apps/app/resources/views/errors/layout.blade.php); change both together.
 */
export type Athlete = 'run' | 'hike' | 'ride';

export type Prop =
    | 'question'
    | 'bang'
    | 'lock'
    | 'key'
    | 'hourglass'
    | 'stopwatch'
    | 'wrench'
    | 'bottle'
    | 'weight';

interface Link {
    label: string;
    href: string;
}

export interface ErrorPage {
    label: string;
    title: string;
    message: string;
    athlete: Athlete;
    prop: Prop;
    jolt: boolean;
    primary: Link;
    secondary: Link;
}

/** The nginx error pages. 404 has its own page at the site root. */
export const staticErrors = [400, 403, 405, 408, 413, 414, 500, 502, 503, 504];

const home: Link = { label: 'Back to the start', href: '/' };
const planner: Link = { label: 'Open the planner', href: '/dashboard' };
// An empty href reloads the address that failed.
const retry: Link = { label: 'Try again', href: '' };

type Entry = Pick<ErrorPage, 'label' | 'title' | 'message' | 'athlete' | 'prop'> &
    Partial<Pick<ErrorPage, 'jolt' | 'primary' | 'secondary'>>;

const pages: Record<number, Entry> = {
    400: {
        label: 'Bad request',
        title: 'That request got tangled',
        message: 'Something in it did not add up. Go back and try again.',
        athlete: 'run',
        prop: 'bang',
    },
    401: {
        label: 'Sign in',
        title: 'Sign in to keep going',
        message: 'This page is for signed-in Traceway users.',
        athlete: 'hike',
        prop: 'key',
        primary: { label: 'Sign in', href: '/login' },
        secondary: home,
    },
    403: {
        label: 'No entry',
        title: 'Private trail',
        message:
            'You cannot open this page. If someone shared it with you, the link may have expired.',
        athlete: 'ride',
        prop: 'lock',
    },
    404: {
        label: 'Not found',
        title: 'Off the map',
        message:
            'We cannot find that page. The link may be old, or the address has a typo.',
        athlete: 'run',
        prop: 'question',
    },
    405: {
        label: 'Wrong way',
        title: 'Wrong way round',
        message: 'That page cannot be opened like that. Go back and try again.',
        athlete: 'run',
        prop: 'bang',
    },
    408: {
        label: 'Timed out',
        title: 'You took a breather',
        message: 'The page waited too long and gave up. Try again.',
        athlete: 'hike',
        prop: 'hourglass',
        primary: retry,
        secondary: home,
    },
    413: {
        label: 'Too heavy',
        title: 'That is a heavy load',
        message: 'That upload is too big to carry. Try a smaller file.',
        athlete: 'hike',
        prop: 'weight',
    },
    414: {
        label: 'Too long',
        title: 'That link is too long',
        message: 'The address is longer than we can read. Try a shorter link.',
        athlete: 'run',
        prop: 'bang',
    },
    419: {
        label: 'Expired',
        title: 'This page went stale',
        message:
            'You were away a while, so the page timed out. Open it again and carry on.',
        athlete: 'hike',
        prop: 'hourglass',
        primary: { label: 'Back to the planner', href: '/dashboard' },
        secondary: home,
    },
    429: {
        label: 'Slow down',
        title: 'Easy on the pace',
        message:
            'That was a lot in a short time. Catch your breath and try again in a minute.',
        athlete: 'run',
        prop: 'stopwatch',
        primary: retry,
        secondary: home,
    },
    500: {
        label: 'Server error',
        title: 'We hit a pothole',
        message: 'Something broke on our side. Try again in a minute.',
        athlete: 'ride',
        prop: 'wrench',
        jolt: true,
        primary: retry,
        secondary: home,
    },
    502: {
        label: 'Bad gateway',
        title: 'Dropped the chain',
        message: 'Our server did not answer. Try again in a minute.',
        athlete: 'ride',
        prop: 'wrench',
        jolt: true,
        primary: retry,
        secondary: home,
    },
    503: {
        label: 'Back soon',
        title: 'At the aid station',
        message: 'Traceway is getting a quick tune-up. Back in a few minutes.',
        athlete: 'run',
        prop: 'bottle',
        primary: retry,
        secondary: home,
    },
    504: {
        label: 'Timed out',
        title: 'Still climbing',
        message: 'Our server is taking too long to answer. Try again in a minute.',
        athlete: 'ride',
        prop: 'hourglass',
        primary: retry,
        secondary: home,
    },
};

const clientError: Entry = {
    label: 'Error',
    title: 'That did not work',
    message: 'Something about that request was off. Go back and try again.',
    athlete: 'run',
    prop: 'bang',
};

/** The words and the scene for a status code. Unknown codes fall back by class. */
export function errorPage(status: number): ErrorPage {
    const entry = pages[status] ?? (status >= 500 ? pages[500] : clientError);

    return {
        jolt: false,
        primary: home,
        secondary: planner,
        ...entry,
    };
}
