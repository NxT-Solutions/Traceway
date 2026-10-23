import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, waitFor, within } from 'storybook/test';
import Devices from '@/pages/settings/devices';
import { destroy, edit, store } from '@/routes/devices';
import type { RouteServiceStatus } from '@/types/route-services';
import { expectToast, type Play, serverTime } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    pathOf,
    redirect,
    sentData,
    validationErrors,
} from '../../support/server';

type Props = ComponentProps<typeof Devices>;

/**
 * Settings, Devices (`/settings/devices`): link a watch or bike computer with the code the Traceway app shows,
 * see the linked ones, and connect the apps Send to device puts routes on.
 *
 * The flow stories run against a mocked server (Mock Service Worker) that answers as DevicesController,
 * LinkDeviceRequest and LinkDeviceHandler do: a code the device is not showing is a 302 back with the error on
 * `code`; a good one is a 302 back with the device in the list, waiting, and the "Device linked" toast. While a
 * device waits the page asks for `devices` again every 3 seconds, and the device reads as linked once it has
 * collected its token. Remove is a DELETE that is a 303 back without it and the "Device removed." toast.
 */
const meta = {
    title: 'Pages/Settings/Devices',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const wahoo: RouteServiceStatus = {
    service: 'wahoo',
    label: 'Wahoo',
    connected: false,
    needs_reconnect: false,
    connected_at: null,
    last_sent_at: null,
};

const rideWithGps: RouteServiceStatus = {
    service: 'ride_with_gps',
    label: 'Ride with GPS',
    connected: false,
    needs_reconnect: false,
    connected_at: null,
    last_sent_at: null,
};

const props: Props = {
    devices: [],
    services: [wahoo, rideWithGps],
    disconnectBlocked: { wahoo: null, ride_with_gps: null },
    emailVerified: true,
    verificationSent: false,
    garminApp:
        'https://apps.garmin.com/apps/a0c32e89-323f-4522-9ee8-c6af6cce14c3',
};

const edge540: Props['devices'][number] = {
    id: '9b2f6c1e-4d7a-4e8b-a1c3-5f0d2e7b8a64',
    label: 'Edge 540',
    provider: 'Garmin',
    linked_at: '2026-09-14T07:12:00Z',
    last_used_at: '2026-10-19T06:45:00Z',
    waiting: false,
};

const forerunner965: Props['devices'][number] = {
    id: '3e8a0d5b-7c2f-41d9-b6e4-0a9c8f1d2b37',
    label: 'Forerunner 965',
    provider: 'Garmin',
    linked_at: '2026-10-02T18:30:00Z',
    last_used_at: null,
    waiting: false,
};

function page(overrides: Partial<Props> = {}) {
    return () => (
        <InertiaPage
            name="settings/devices"
            component={Devices}
            props={{ ...props, ...overrides }}
            shared={sharedProps()}
            url={edit.url()}
        />
    );
}

export const NoDevices: Story = {
    render: page(),
    play: async ({ canvasElement }) => {
        const store = within(canvasElement).getByRole('link', {
            name: /Get Traceway in Garmin's Connect IQ store/,
        });

        await expect(store).toHaveAttribute('href', props.garminApp);
        await expect(store).toHaveAttribute('target', '_blank');
        await expect(
            within(canvasElement).getByRole('heading', {
                level: 3,
                name: 'Where Traceway is on the device',
            }),
        ).toBeVisible();
    },
};

/** Two Garmins linked, and Wahoo connected with a route sent. */
export const LinkedDevices: Story = {
    render: page({
        devices: [edge540, forerunner965],
        services: [
            {
                ...wahoo,
                connected: true,
                connected_at: '2026-09-20T09:00:00Z',
                last_sent_at: '2026-10-18T17:20:00Z',
            },
            rideWithGps,
        ],
    }),
};

/** Wahoo took its keys back, and the only way to log in is that connection, so it cannot be disconnected. */
export const NeedsReconnect: Story = {
    render: page({
        services: [
            {
                ...wahoo,
                connected: true,
                needs_reconnect: true,
                connected_at: '2026-09-20T09:00:00Z',
                last_sent_at: '2026-10-01T12:00:00Z',
            },
            rideWithGps,
        ],
        disconnectBlocked: {
            wahoo: 'Add a password or a passkey first, so you can still log in.',
            ride_with_gps: null,
        },
    }),
};

/**
 * Answers the page's poll for its devices. Inertia reloads the address the page is on: /settings/devices in the app,
 * the story's own address here.
 */
function devicesPoll(devices: Props['devices']) {
    return http.get('*', ({ request }) =>
        request.headers.get('X-Inertia-Partial-Component') ===
        'settings/devices'
            ? inertiaPage(
                  'settings/devices',
                  { ...props, devices },
                  { url: edit.url(), request },
              )
            : undefined,
    );
}

/** The Edge 540 the moment its code was typed, before it collected its token. */
const waitingEdge: Props['devices'][number] = {
    ...edge540,
    last_used_at: null,
    waiting: true,
};

/** The code was typed, and the Edge 540 has not collected its token yet. The page asks every 3 seconds. */
export const WaitingForDevice: Story = {
    render: page({ devices: [waitingEdge] }),
    beforeEach({ msw }) {
        msw.use(devicesPoll([waitingEdge]));
    },
};

/** Connect and Disconnect wait until the rider confirms their email. */
export const EmailNotConfirmed: Story = {
    render: page({ emailVerified: false }),
};

/** The codes the mocked server got. */
const linkRequest = fn();

/** DevicesController@store: records what it got, then sends the rider back to the page. */
const linkRoute = http.post(store.url(), async ({ request }) => {
    linkRequest(await sentData(request));
    await network();

    return redirect(edit.url());
});

/** Sam types the code the Edge 540 shows, names it, and links it. */
async function linkEdge({ canvas, userEvent }: Play, code: string) {
    await userEvent.type(canvas.getByLabelText('Code on the device'), code);

    const name = canvas.getByLabelText('Name');

    await userEvent.clear(name);
    await userEvent.type(name, 'Edge 540');
    await userEvent.click(canvas.getByRole('button', { name: 'Link device' }));
}

/** The code is not one a device is showing now, so the error says to open the app for a new one. */
export const WrongCode: Story = {
    render: page(),
    beforeEach({ msw }) {
        linkRequest.mockClear();
        msw.use(
            linkRoute,
            http.get(edit.url(), () =>
                validationErrors(
                    'settings/devices',
                    props,
                    {
                        code: 'That code is not waiting. Open Traceway on the device for a new one.',
                    },
                    { url: edit.url() },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type an old code', async () => {
            await linkEdge(context, 'xk7p2m');
        });

        await step('The error shows under the code', async () => {
            await expect(
                await canvas.findByText(
                    'That code is not waiting. Open Traceway on the device for a new one.',
                ),
            ).toBeVisible();
            // The field writes the code in capitals, as the device shows it.
            await expect(linkRequest).toHaveBeenCalledWith({
                code: 'XK7P2M',
                label: 'Edge 540',
            });
            await expect(
                canvas.getByText('No devices linked yet.'),
            ).toBeVisible();
        });
    },
};

/**
 * The right code: the Edge 540 joins the list as waiting, a toast says so, and the form clears for the next one.
 * The page asks again until the device has collected its token, and then it reads as linked.
 */
export const LinksDevice: Story = {
    render: page(),
    beforeEach({ msw }) {
        linkRequest.mockClear();
        msw.use(
            linkRoute,
            // The redirect after the link finds the device waiting; the poll finds it linked.
            http.get(edit.url(), () =>
                inertiaPage(
                    'settings/devices',
                    { ...props, devices: [waitingEdge] },
                    {
                        url: edit.url(),
                        flash: {
                            toast: {
                                type: 'success',
                                message:
                                    'Device linked. It will show your routes in a moment.',
                            },
                        },
                    },
                ),
            ),
            devicesPoll([{ ...waitingEdge, waiting: false }]),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type the code the Edge 540 shows', async () => {
            await linkEdge(context, '7KQ4MZ');
        });

        await step('The Edge 540 waits in the list', async () => {
            await expectToast(
                canvas,
                'Device linked. It will show your routes in a moment.',
            );

            await expect(canvas.getByText('Edge 540')).toBeVisible();
            await expect(
                canvas.getByText(/Waiting for the device/),
            ).toBeVisible();
            await expect(
                canvas.queryByText('No devices linked yet.'),
            ).toBeNull();
            await expect(
                canvas.getByLabelText('Code on the device'),
            ).toHaveValue('');
            await expect(canvas.getByLabelText('Name')).toHaveValue('Garmin');
        });

        await step(
            'The device collects its token and reads as linked',
            async () => {
                await waitFor(
                    () =>
                        expect(
                            canvas.queryByText(/Waiting for the device/),
                        ).toBeNull(),
                    { timeout: 6000 },
                );
                await expect(
                    canvas.getByText(/Linked .* Last used Not yet\./),
                ).toBeVisible();
            },
        );
    },
};

/** The code goes in six boxes, in capitals as the device shows it, and a pasted code with a dash fits. */
export const CodeBoxes: Story = {
    render: page(),
    play: async ({ canvas, userEvent, step }) => {
        const code = canvas.getByLabelText('Code on the device');

        await step('Lower case comes out in capitals', async () => {
            await userEvent.type(code, 'xk7');
            await expect(code).toHaveValue('XK7');
        });

        await step(
            'A 0, O, 1, I, or L is never on the device, so it is left out',
            async () => {
                await userEvent.type(code, '0o1il');
                await expect(code).toHaveValue('XK7');
            },
        );

        await step('A pasted code drops its dash', async () => {
            await userEvent.clear(code);
            await userEvent.click(code);
            await userEvent.paste('xk7-p2m');
            await expect(code).toHaveValue('XK7P2M');
        });
    },
};

/** Which device the mocked server removed. */
const removeRequest = fn();

/** Sam removes the Edge 540; the Forerunner 965 stays, and its access is untouched. */
export const RemovesDevice: Story = {
    render: LinkedDevices.render,
    beforeEach({ msw }) {
        removeRequest.mockClear();
        msw.use(
            http.delete(pathOf(destroy), async ({ params }) => {
                removeRequest(params.deviceLink);
                await network();

                return redirect(edit.url(), 303);
            }),
            http.get(edit.url(), () =>
                inertiaPage(
                    'settings/devices',
                    { ...props, devices: [forerunner965] },
                    {
                        url: edit.url(),
                        flash: {
                            toast: {
                                type: 'success',
                                message: 'Device removed.',
                            },
                        },
                    },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Remove the Edge 540', async () => {
            const edge = canvas.getByText('Edge 540').closest('li');

            await expect(edge).not.toBeNull();
            await userEvent.click(
                within(edge as HTMLElement).getByRole('button', {
                    name: 'Remove',
                }),
            );
        });

        await step('Only the Forerunner 965 is left', async () => {
            await expectToast(canvas, 'Device removed.');
            await waitFor(
                () => expect(canvas.queryByText('Edge 540')).toBeNull(),
                serverTime,
            );
            await expect(canvas.getByText('Forerunner 965')).toBeVisible();
            await expect(removeRequest).toHaveBeenCalledWith(edge540.id);
        });
    },
};
