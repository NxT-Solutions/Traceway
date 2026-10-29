import { router } from '@inertiajs/react';
import type { Decorator, Meta, StoryObj } from '@storybook/react-vite';
import { useEffect } from 'react';
import { expect, fn, userEvent } from 'storybook/test';
import Status, { type Range, type StatusPage } from '@/pages/status';
import { status } from '@/routes';
import { InertiaPage, sharedProps } from '../support/inertia-page';
import { http, inertiaPage } from '../support/server';
import { followLinks } from '../support/story-links';

// The page reads its props with usePage() and takes none of its own; these mirror ServiceStatusQuery::page().
type State = Exclude<StatusPage['state'], 'unknown'>;

type Component = StatusPage['groups'][number]['components'][number];

type Bar = Component['bars'][number];

/**
 * The page polls every minute. A story without a mocked server turns down every visit while it shows; one with
 * `parameters.mockedServer` lets them through to its handlers.
 */
const noVisits: Decorator = (Story, { parameters }) => {
    const mocked = parameters.mockedServer === true;

    useEffect(
        () => (mocked ? undefined : router.on('before', () => false)),
        [mocked],
    );

    return <Story />;
};

/**
 * The public status page (`/status`, no layout): whether the planner, the map services, the apps a rider sends to,
 * and the Garmin app work, in bars. It opens on the last 24 hours, an hour to a bar; the picker shows 7, 30, or 90
 * days by the day, or a year by the week.
 *
 * `RecoversOnNextCheck` answers the page's minute poll from a mocked server (Mock Service Worker), as
 * ShowStatusController does for a partial reload: only the props the poll asks for. `PicksARange` does the same for
 * a range picked. "Open the planner" opens the planner's story.
 */
const meta = {
    title: 'Pages/Status',
    parameters: { layout: 'fullscreen' },
    decorators: [
        noVisits,
        followLinks({ '/dashboard': ['Pages/Planner/Dashboard', 'Empty'] }),
    ],
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const HOUR = 3_600_000;
const DAY = 86_400_000;
const now = Date.now();
const ago = (ms: number) => new Date(now - ms).toISOString();

/** Each range's bars, as ServiceStatusQuery sends them: how many, and how long each is. */
const shape: Record<Range, { bars: number; each: number }> = {
    day: { bars: 24, each: HOUR },
    week: { bars: 7, each: DAY },
    month: { bars: 30, each: DAY },
    quarter: { bars: 90, each: DAY },
    year: { bars: 52, each: 7 * DAY },
};

/** A range's bars, oldest first. `bad` maps bars ago to a state; the rest are `fill`. */
function bars(
    range: Range = 'day',
    bad: Record<number, State> = {},
    fill: State | null = 'up',
): Bar[] {
    const { bars: count, each } = shape[range];
    const last =
        range === 'day'
            ? Math.floor(now / HOUR) * HOUR
            : Math.floor(now / DAY) * DAY - (range === 'year' ? 6 * DAY : 0);

    return Array.from({ length: count }, (_, index) => {
        const barsAgo = count - 1 - index;

        return {
            start: new Date(last - barsAgo * each)
                .toISOString()
                .replace(/\.\d{3}Z$/, 'Z'),
            state: bad[barsAgo] ?? fill,
        };
    });
}

function component(
    key: string,
    label: string,
    latency: number,
    overrides: Partial<Component> = {},
    range: Range = 'day',
): Component {
    return {
        key,
        label,
        state: 'up',
        summary: `${label} works`,
        latency_ms: latency,
        uptime: 100,
        bars: bars(range),
        ...overrides,
    };
}

/** Route drawing went down this morning, or in the range's last bars, and was slow once before. */
function groups(
    overrides: Partial<Record<string, Partial<Component>>> = {},
    range: Range = 'day',
) {
    return [
        {
            key: 'traceway',
            label: 'Traceway',
            components: [
                component('planner', 'Planner', 120, overrides.planner, range),
            ],
        },
        {
            key: 'maps',
            label: 'Map services',
            components: [
                component(
                    'routing',
                    'Route drawing',
                    840,
                    {
                        uptime: 99.86,
                        bars: bars(
                            range,
                            range === 'day'
                                ? { 3: 'down', 4: 'down' }
                                : { 1: 'down', 6: 'slow' },
                        ),
                        ...overrides.routing,
                    },
                    range,
                ),
                component(
                    'places',
                    'Place search',
                    310,
                    overrides.places,
                    range,
                ),
                component('tiles', 'Maps', 90, overrides.tiles, range),
            ],
        },
        {
            key: 'apps',
            label: 'Send to apps',
            components: [
                component(
                    'ride_with_gps',
                    'Ride with GPS',
                    420,
                    overrides.ride_with_gps,
                    range,
                ),
                component('wahoo', 'Wahoo', 360, overrides.wahoo, range),
            ],
        },
        {
            key: 'devices',
            label: 'Devices',
            components: [
                component(
                    'garmin_store',
                    'Garmin app in the Connect IQ store',
                    610,
                    {
                        summary: 'The Garmin app is in the Connect IQ store',
                        ...overrides.garmin_store,
                    },
                    range,
                ),
            ],
        },
    ];
}

function page(props: StatusPage) {
    return () => (
        <InertiaPage
            name="status"
            component={Status}
            props={props}
            shared={sharedProps()}
            url="/status"
        />
    );
}

/** Everything answered at the last check. A notice from last week is resolved. */
export const AllWorking: Story = {
    render: page({
        state: 'up',
        headline: 'Everything works',
        checked_at: ago(3 * 60_000),
        stale: false,
        range: 'day',
        notices: [
            {
                id: 7,
                title: 'Route drawing was down',
                body: 'The public routing server stopped answering for about 40 minutes. Saved routes and course files were not affected.',
                components: ['routing'],
                started_at: ago(5 * DAY),
                resolved_at: ago(5 * DAY - 40 * 60_000),
            },
        ],
        groups: groups(),
    }),
};

/** Route drawing is slow, with a notice that says why. */
export const RouteDrawingSlow: Story = {
    render: page({
        state: 'slow',
        headline: 'Route drawing is slow right now',
        checked_at: ago(2 * 60_000),
        stale: false,
        range: 'day',
        notices: [
            {
                id: 8,
                title: 'Route drawing is slower than usual',
                body: 'The public routing server is busy. Routes still come, a few seconds later.',
                components: ['routing'],
                started_at: ago(45 * 60_000),
                resolved_at: null,
            },
        ],
        groups: groups({
            routing: {
                state: 'slow',
                summary: 'Route drawing is slow right now',
                latency_ms: 6400,
                bars: bars('day', { 0: 'slow', 3: 'down', 4: 'down' }),
            },
        }),
    }),
};

const wahooDown: StatusPage = {
    state: 'down',
    headline: 'Wahoo is down right now',
    checked_at: ago(60_000),
    stale: false,
    range: 'day',
    notices: [],
    groups: groups({
        wahoo: {
            state: 'down',
            summary: 'Wahoo is down right now',
            latency_ms: null,
            uptime: 99.31,
            bars: bars('day', { 0: 'down' }),
        },
    }),
};

/** Wahoo cannot be reached, so Send to device cannot put routes there. */
export const WahooDown: Story = { render: page(wahooDown) };

/** The polls the mocked server answered, with the props each asked for. */
const pollRequest = fn();

/**
 * Wahoo is down; at the next check it answers again. The page asks the server every minute (`usePoll`); the story
 * makes the same partial reload at once rather than wait a minute, and the headline and Wahoo's row update in place.
 */
export const RecoversOnNextCheck: Story = {
    render: page(wahooDown),
    parameters: { mockedServer: true },
    beforeEach({ msw }) {
        pollRequest.mockClear();
        msw.use(
            // The poll reloads the address the page is on: /status in the app, the story's own address here.
            http.get('*', ({ request }) => {
                if (
                    request.headers.get('X-Inertia-Partial-Component') !==
                    'status'
                ) {
                    return undefined;
                }

                pollRequest(request.headers.get('X-Inertia-Partial-Data'));

                return inertiaPage(
                    'status',
                    {
                        state: 'up',
                        headline: 'Everything works',
                        checked_at: new Date().toISOString(),
                        stale: false,
                        range: 'day',
                        notices: [],
                        groups: groups({
                            wahoo: {
                                uptime: 99.31,
                                bars: bars('day', { 1: 'down' }),
                            },
                        }),
                    },
                    { url: status.url(), request },
                );
            }),
        );
    },
    play: async ({ canvas, step }) => {
        await step('Wahoo is down', async () => {
            await expect(
                canvas.getByRole('heading', {
                    level: 1,
                    name: 'Wahoo is down right now',
                }),
            ).toBeVisible();
        });

        await step('A minute later, the page asks again', async () => {
            router.reload({
                only: [
                    'state',
                    'headline',
                    'checked_at',
                    'stale',
                    'notices',
                    'groups',
                ],
            });
        });

        await step('Everything works again', async () => {
            await expect(
                await canvas.findByRole('heading', {
                    level: 1,
                    name: 'Everything works',
                }),
            ).toBeVisible();
            await expect(pollRequest).toHaveBeenCalledWith(
                'state,headline,checked_at,stale,notices,groups',
            );
        });
    },
};

/** A new install, before the first check has run. */
export const NoChecksYet: Story = {
    render: page({
        state: 'unknown',
        headline: 'No checks yet',
        checked_at: null,
        stale: false,
        range: 'day',
        notices: [],
        groups: groups(
            Object.fromEntries(
                [
                    'planner',
                    'routing',
                    'places',
                    'tiles',
                    'ride_with_gps',
                    'wahoo',
                    'garmin_store',
                ].map((key) => [
                    key,
                    {
                        state: null,
                        summary: 'Not checked yet',
                        latency_ms: null,
                        uptime: null,
                        bars: bars('day', {}, null),
                    },
                ]),
            ),
        ),
    }),
};

/** A range's page: everything works now, and route drawing was down in the range. */
function inRange(range: Range): StatusPage {
    return {
        state: 'up',
        headline: 'Everything works',
        checked_at: ago(3 * 60_000),
        stale: false,
        range,
        notices: [],
        groups: groups({}, range),
    };
}

/** The last 90 days, a day to a bar: a phone shows the last 30. */
export const LastNinetyDays: Story = { render: page(inRange('quarter')) };

/** The last year, a week to a bar. */
export const LastYear: Story = { render: page(inRange('year')) };

/** The ranges the mocked server was asked for. */
const rangeRequest = fn();

/**
 * The page opens on the last 24 hours. Picking 7 days asks the server for the range and the bars only, and the bars
 * turn into days.
 */
export const PicksARange: Story = {
    render: page(inRange('day')),
    parameters: { mockedServer: true },
    beforeEach({ msw }) {
        rangeRequest.mockClear();
        msw.use(
            http.get('*', ({ request }) => {
                if (
                    request.headers.get('X-Inertia-Partial-Component') !==
                    'status'
                ) {
                    return undefined;
                }

                const range = (new URL(request.url).searchParams.get('range') ??
                    'day') as Range;
                rangeRequest(
                    range,
                    request.headers.get('X-Inertia-Partial-Data'),
                );

                return inertiaPage('status', inRange(range), {
                    url: status.url({ query: { range } }),
                    request,
                });
            }),
        );
    },
    play: async ({ canvas, step }) => {
        await step('It opens on the last 24 hours', async () => {
            await expect(
                canvas.getByRole('link', { name: '24 hours' }),
            ).toHaveAttribute('aria-current', 'true');
        });

        await step('Pick 7 days', async () => {
            await userEvent.click(canvas.getByRole('link', { name: '7 days' }));
        });

        await step('The bars are days now', async () => {
            await expect(
                await canvas.findByRole('link', {
                    name: '7 days',
                    current: true,
                }),
            ).toBeVisible();
            await expect(rangeRequest).toHaveBeenCalledWith(
                'week',
                'range,groups',
            );
            await expect(
                canvas.getAllByText('7 days ago').length,
            ).toBeGreaterThan(0);
        });
    },
};
