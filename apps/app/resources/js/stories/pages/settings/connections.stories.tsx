import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, screen, waitFor, within } from 'storybook/test';
import Connections from '@/pages/settings/connections';
import { destroy, edit } from '@/routes/connections';
import type { ConnectedAccount, ConnectionDescription } from '@/types';
import { expectToast, serverTime } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    pathOf,
    redirect,
} from '../../support/server';

type Props = ComponentProps<typeof Connections>;

/**
 * Settings, Connections (`/settings/connections`): the accounts at other services a rider logs in with or sends
 * routes to, each with what Traceway keeps from it, and the ones they can still add.
 *
 * `DisconnectsWahoo` runs against a mocked server (Mock Service Worker) that answers as ConnectionsController does:
 * the DELETE is a 303 back to `/settings/connections` with the service moved to Add a connection and the
 * "disconnected" toast. Connect leaves Traceway for the service's own sign-in, so no story follows it.
 */
const meta = {
    title: 'Pages/Settings/Connections',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const logIn = { key: 'sign_in', label: 'Log in', later: false } as const;
const sendRoutes = {
    key: 'send_routes',
    label: 'Send routes',
    later: false,
} as const;

/** What ConnectionsQuery::describe() sends for each service. */
const wahoo: ConnectionDescription = {
    slug: 'wahoo',
    label: 'Wahoo',
    group: 'sport',
    group_label: 'Sport apps',
    purpose: 'Log in with Wahoo, and send routes to your ELEMNT or ACE.',
    note: null,
    keeps: [
        'Your Wahoo account number and email address.',
        'The keys Wahoo gave Traceway, kept encrypted, to send routes.',
        'Which routes you sent there.',
    ],
    capabilities: [
        logIn,
        sendRoutes,
        { key: 'sync_routes', label: 'Keep routes in sync', later: true },
    ],
    available: true,
    connected: false,
};

const rideWithGps: ConnectionDescription = {
    slug: 'ride_with_gps',
    label: 'Ride with GPS',
    group: 'sport',
    group_label: 'Sport apps',
    purpose:
        'Log in with Ride with GPS, and send routes to your library there.',
    note: null,
    keeps: [
        'Your Ride with GPS account number and email address.',
        'The key Ride with GPS gave Traceway, kept encrypted, to send routes.',
        'Which routes you sent there.',
    ],
    capabilities: [
        logIn,
        sendRoutes,
        { key: 'import_routes', label: 'Bring in routes', later: true },
    ],
    available: true,
    connected: false,
};

const runSignup: ConnectionDescription = {
    slug: 'runsignup',
    label: 'RunSignup',
    group: 'sport',
    group_label: 'Sport apps',
    purpose: 'Log in with your RunSignup account.',
    note: 'The account you sign up for races with.',
    keeps: [
        'Your RunSignup account number and email address.',
        'No races, and no access to your RunSignup account.',
    ],
    capabilities: [
        logIn,
        { key: 'notifications', label: 'Notifications', later: true },
    ],
    available: true,
    connected: false,
};

const google: ConnectionDescription = {
    slug: 'google',
    label: 'Google',
    group: 'accounts',
    group_label: 'Everyday accounts',
    purpose: 'Log in with your Google account.',
    note: null,
    keeps: [
        'Your Google account number and email address.',
        'No password, and no access to your Google account.',
    ],
    capabilities: [logIn],
    available: true,
    connected: false,
};

const github: ConnectionDescription = {
    slug: 'github',
    label: 'GitHub',
    group: 'accounts',
    group_label: 'Everyday accounts',
    purpose: 'Log in with your GitHub account.',
    note: null,
    keeps: [
        'Your GitHub account number and username.',
        'No password, and no access to your code.',
    ],
    capabilities: [logIn],
    available: true,
    connected: false,
};

const discord: ConnectionDescription = {
    slug: 'discord',
    label: 'Discord',
    group: 'accounts',
    group_label: 'Everyday accounts',
    purpose: 'Log in with your Discord account.',
    note: null,
    keeps: [
        'Your Discord account number and username.',
        'No password, and no access to your messages.',
    ],
    capabilities: [
        logIn,
        { key: 'notifications', label: 'Notifications', later: true },
    ],
    available: true,
    connected: false,
};

const openStreetMap: ConnectionDescription = {
    slug: 'openstreetmap',
    label: 'OpenStreetMap',
    group: 'maps',
    group_label: 'Maps',
    purpose: 'Log in with the account you map with.',
    note: null,
    keeps: [
        'Your OpenStreetMap account number and display name.',
        'No home location, and no access to your edits.',
    ],
    capabilities: [
        logIn,
        { key: 'map_notes', label: 'Report map problems', later: true },
    ],
    available: true,
    connected: false,
};

const connectedWahoo: ConnectedAccount = {
    ...wahoo,
    connected: true,
    account_label: 'sam@example.com',
    needs_reconnect: false,
    connected_at: '2026-09-20T09:00:00Z',
    last_signed_in_at: '2026-10-12T07:30:00Z',
    last_sent_at: '2026-10-18T17:20:00Z',
    manage_url: null,
    withdraws_access: true,
    can_disconnect: true,
    disconnect_blocked: null,
};

const connectedGoogle: ConnectedAccount = {
    ...google,
    connected: true,
    account_label: 'sam@example.com',
    needs_reconnect: false,
    connected_at: '2026-09-01T08:00:00Z',
    last_signed_in_at: '2026-10-19T06:40:00Z',
    last_sent_at: null,
    manage_url: 'https://myaccount.google.com/connections',
    withdraws_access: false,
    can_disconnect: true,
    disconnect_blocked: null,
};

const everyService = [
    wahoo,
    rideWithGps,
    runSignup,
    google,
    github,
    discord,
    openStreetMap,
];

const props: Props = {
    connected: [],
    available: everyService,
    hasPassword: true,
    hasPasskey: false,
    emailVerified: true,
    verificationSent: false,
};

function page(overrides: Partial<Props> = {}) {
    return () => (
        <InertiaPage
            name="settings/connections"
            component={Connections}
            props={{ ...props, ...overrides }}
            shared={sharedProps()}
            url="/settings/connections"
        />
    );
}

export const NothingConnected: Story = { render: page() };

/** Wahoo sends routes; Google logs the rider in. The rest wait under Add a connection. */
export const Connected: Story = {
    render: page({
        connected: [connectedWahoo, connectedGoogle],
        available: [rideWithGps, runSignup, github, discord, openStreetMap],
    }),
};

/**
 * The rider opened the account with Google and has no password or passkey, so Google is their only way in and
 * cannot be disconnected yet.
 */
export const LastWayToLogIn: Story = {
    render: page({
        connected: [
            {
                ...connectedGoogle,
                can_disconnect: false,
                disconnect_blocked:
                    'Add a password or a passkey first, so you can still log in.',
            },
        ],
        available: [
            wahoo,
            rideWithGps,
            runSignup,
            github,
            discord,
            openStreetMap,
        ],
        hasPassword: false,
    }),
};

/** Wahoo took its keys back; the rider connects it again. */
export const NeedsReconnect: Story = {
    render: page({
        connected: [{ ...connectedWahoo, needs_reconnect: true }],
        available: [
            rideWithGps,
            runSignup,
            google,
            github,
            discord,
            openStreetMap,
        ],
    }),
};

/** Connect and Disconnect wait until the rider confirms their email. */
export const EmailNotConfirmed: Story = {
    render: page({
        connected: [connectedWahoo],
        available: [
            rideWithGps,
            runSignup,
            google,
            github,
            discord,
            openStreetMap,
        ],
        emailVerified: false,
    }),
};

/** No service is set up on this Traceway. */
export const NoServicesSetUp: Story = { render: page({ available: [] }) };

/** Which service the mocked server disconnected. */
const disconnectRequest = fn();

/**
 * Sam disconnects Wahoo. The dialog says what happens first; Cancel keeps it, and a second try goes through. Wahoo
 * moves to Add a connection, a toast says so, and focus goes to that list.
 */
export const DisconnectsWahoo: Story = {
    render: Connected.render,
    beforeEach({ msw }) {
        disconnectRequest.mockClear();
        msw.use(
            http.delete(pathOf(destroy), async ({ params }) => {
                disconnectRequest(params.provider);
                await network();

                return redirect(edit.url(), 303);
            }),
            http.get(edit.url(), () =>
                inertiaPage(
                    'settings/connections',
                    {
                        ...props,
                        connected: [connectedGoogle],
                        available: [
                            wahoo,
                            rideWithGps,
                            runSignup,
                            github,
                            discord,
                            openStreetMap,
                        ],
                    },
                    {
                        url: edit.url(),
                        flash: {
                            toast: {
                                type: 'success',
                                message: 'Wahoo disconnected.',
                            },
                        },
                    },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        const openDialog = async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Disconnect Wahoo' }),
            );

            const dialog = await screen.findByRole('dialog');

            // The dialog fades in.
            await waitFor(() => expect(dialog).toBeVisible());

            return within(dialog);
        };

        await step('Ask to disconnect, then cancel', async () => {
            const dialog = await openDialog();

            await expect(
                dialog.getByText(
                    /Traceway also asks Wahoo to remove its access/,
                ),
            ).toBeVisible();
            await userEvent.click(
                dialog.getByRole('button', { name: 'Cancel' }),
            );
            await waitFor(() =>
                expect(screen.queryByRole('dialog')).toBeNull(),
            );
            await expect(disconnectRequest).not.toHaveBeenCalled();
        });

        await step('Ask again, and disconnect', async () => {
            const dialog = await openDialog();

            await userEvent.click(
                dialog.getByRole('button', { name: 'Disconnect Wahoo' }),
            );
        });

        await step('Wahoo moves to Add a connection', async () => {
            await expectToast(canvas, 'Wahoo disconnected.');
            await expect(disconnectRequest).toHaveBeenCalledWith('wahoo');
            await waitFor(
                () =>
                    expect(
                        canvas.queryByRole('button', {
                            name: 'Disconnect Wahoo',
                        }),
                    ).toBeNull(),
                serverTime,
            );
            await waitFor(() =>
                expect(document.activeElement?.id).toBe('connections-add'),
            );
        });
    },
};
