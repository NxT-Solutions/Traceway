import { expect, within } from 'storybook/test';
import ErrorPage from '../../components/ErrorPage.astro';
import { errorPage } from '../../errors';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/** Every status the site and nginx have words for (src/errors.ts). Others fall back by class. */
const statuses = [
    400, 401, 403, 404, 405, 408, 413, 414, 419, 429, 500, 502, 503, 504,
];

/**
 * A whole error page: a night trail with an athlete who keeps moving, and a race bib with the
 * status on it. Each status has its own words, athlete (run, hike or ride) and prop. The site uses
 * it for 404 and for the pages nginx serves when the app is down (src/pages/errors). It loads only
 * errors.css, so it shows even when the site's own styles cannot load.
 */
const meta = {
    title: 'Components/ErrorPage',
    component: ErrorPage,
    parameters: {
        sheets: 'errors',
        // The page puts the athlete, and a jolt for a server error, on <body>.
        bodyClass: ({ status }: { status?: number }) => {
            const page = errorPage(Number(status ?? 404));

            return `is-${page.athlete}${page.jolt ? ' jolt' : ''}`;
        },
    },
    args: { status: 404 },
    argTypes: {
        status: {
            description:
                'The HTTP status. It picks the words, the athlete and the prop.',
            control: 'select',
            options: statuses,
        },
    },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta.args>;

/** An address the site does not have. A runner with a question mark. */
export const NotFound: Story = {
    name: '404',
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(page.getByRole('heading', { level: 1, name: 'Off the map' }));
        await expect(
            page.getByRole('link', { name: 'Back to the start' }),
        ).toHaveAttribute('href', '/');
        await expect(
            page.getByRole('link', { name: 'Open the planner' }),
        ).toHaveAttribute('href', '/dashboard');
    },
};

/** Something broke on the server. A rider jolts over a pothole, and Try again reloads. */
export const ServerError: Story = {
    name: '500',
    args: { status: 500 },
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(page.getByRole('heading', { level: 1, name: 'We hit a pothole' }));
        // An empty address reloads the one that failed.
        await expect(page.getByText('Try again')).toHaveAttribute('href', '');
    },
};

/** Sign in first. A hiker with a key. */
export const SignIn: Story = { name: '401', args: { status: 401 } };

/** A private page. A rider with a lock. */
export const Forbidden: Story = { name: '403', args: { status: 403 } };

/** Too many requests. A runner with a stopwatch, out of breath. */
export const TooManyRequests: Story = { name: '429', args: { status: 429 } };

/** Down for a tune-up. A runner at the aid station. */
export const Maintenance: Story = { name: '503', args: { status: 503 } };

export const Phone: Story = {
    name: '404 on a phone',
    globals: { viewport: { value: 'phone', isRotated: false } },
};
