import { router } from '@inertiajs/react';
import type { Decorator, Meta, StoryObj } from '@storybook/react-vite';
import { useEffect } from 'react';
import { expect, fn } from 'storybook/test';
import Status, { type StatusPage } from '@/pages/status';
import { status } from '@/routes';
import { InertiaPage, sharedProps } from '../support/inertia-page';
import { http, inertiaPage } from '../support/server';
import { followLinks } from '../support/story-links';

// The page reads its props with usePage() and takes none of its own; these mirror ServiceStatusQuery::page().
type State = Exclude<StatusPage['state'], 'unknown'>;

type Component = StatusPage['groups'][number]['components'][number];

type Day = Component['days'][number];

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
 * and the Garmin app work, with the last 90 days in bars.
 *
 * `RecoversOnNextCheck` answers the page's minute poll from a mocked server (Mock Service Worker), as
 * ShowStatusController does for a partial reload: only the props the poll asks for. "Open the planner" opens the
 * planner's story.
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

const DAY = 86_400_000;
const now = Date.now();
const ago = (ms: number) => new Date(now - ms).toISOString();

/** The last 90 days, oldest first. `bad` maps days ago to a state; the rest are `fill`. */
function days(
    bad: Record<number, State> = {},
    fill: State | null = 'up',
): Day[] {
    return Array.from({ length: 90 }, (_, index) => {
        const daysAgo = 89 - index;

        return {
            date: ago(daysAgo * DAY).slice(0, 10),
            state: bad[daysAgo] ?? fill,
        };
    });
}

function component(
    key: string,
    label: string,
    latency: number,
    overrides: Partial<Component> = {},
): Component {
    return {
        key,
        label,
        state: 'up',
        summary: `${label} works`,
        latency_ms: latency,
        uptime: 100,
        days: days(),
        ...overrides,
    };
}

function groups(overrides: Partial<Record<string, Partial<Component>>> = {}) {
    return [
        {
            key: 'traceway',
            label: 'Traceway',
            components: [
                component('planner', 'Planner', 120, overrides.planner),
            ],
        },
        {
            key: 'maps',
            label: 'Map services',
            components: [
                component('routing', 'Route drawing', 840, {
                    uptime: 99.86,
                    days: days({ 23: 'down', 41: 'slow' }),
                    ...overrides.routing,
                }),
                component('places', 'Place search', 310, overrides.places),
                component('tiles', 'Maps', 90, overrides.tiles),
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
                ),
                component('wahoo', 'Wahoo', 360, overrides.wahoo),
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
                days: days({ 0: 'slow', 23: 'down', 41: 'slow' }),
            },
        }),
    }),
};

const wahooDown: StatusPage = {
    state: 'down',
    headline: 'Wahoo is down right now',
    checked_at: ago(60_000),
    stale: false,
    notices: [],
    groups: groups({
        wahoo: {
            state: 'down',
            summary: 'Wahoo is down right now',
            latency_ms: null,
            uptime: 99.31,
            days: days({ 0: 'down' }),
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
                        notices: [],
                        groups: groups({
                            wahoo: {
                                uptime: 99.31,
                                days: days({ 0: 'down' }, 'up'),
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
                        days: days({}, null),
                    },
                ]),
            ),
        ),
    }),
};
