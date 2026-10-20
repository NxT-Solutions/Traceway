import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { expect, fn, screen, waitFor, within } from 'storybook/test';
import { type DeviceLeg, SendToDevice } from '@/components/send-to-device';
import { Button } from '@/components/ui/button';
import type { RouteServiceSlug, RouteServiceStatus } from '@/types';
import { rider } from '../support/fixtures';
import { InertiaPage, sharedProps } from '../support/inertia-page';
import { HttpResponse, http } from '../support/server';

const brandKey = 'traceway.send-to-device.brand';

/** Remembers a brand the way the dialog does, so the story opens on its steps. */
function pickBrand(brand: string | null): () => void {
    return () => {
        try {
            if (brand === null) {
                window.localStorage.removeItem(brandKey);
            } else {
                window.localStorage.setItem(brandKey, brand);
            }
        } catch {
            // Private windows may refuse storage; the dialog opens on the brands.
        }
    };
}

/** Where the dialog sends a saved route to a service. */
const sendRoute = '*/routes/saved/:id/send/:service';

const wahooConnected: RouteServiceStatus = {
    service: 'wahoo',
    label: 'Wahoo',
    connected: true,
    needs_reconnect: false,
    connected_at: '2026-10-02T07:30:00Z',
    last_sent_at: '2026-10-19T06:45:00Z',
};

const rideWithGps: RouteServiceStatus = {
    service: 'ride_with_gps',
    label: 'Ride with GPS',
    connected: false,
    needs_reconnect: false,
    connected_at: null,
    last_sent_at: null,
};

type Callbacks = {
    onOpenChange: (open: boolean) => void;
    onSent: (service: RouteServiceSlug, routeId: string) => void;
};

/**
 * The story's spies, for the page Inertia draws: Inertia keeps a page's props in the browser history, where only
 * plain data fits, so the callbacks reach it here instead.
 */
const callbacks: Callbacks = {
    onOpenChange: () => undefined,
    onSent: () => undefined,
};

/** The download buttons the library passes in, one per file. */
function Download({ format }: { format: 'fit' | 'gpx' }) {
    return (
        <Button asChild>
            <a href={`#morning-loop.${format}`}>
                Download {format.toUpperCase()}
            </a>
        </Button>
    );
}

/** A saved route's actions, with the dialog open. */
function SavedRoute({ legs }: { legs: DeviceLeg[] }) {
    const [open, setOpen] = useState(true);

    return (
        <div className="p-6">
            <Button variant="outline" onClick={() => setOpen(true)}>
                Send to device
            </Button>
            <SendToDevice
                open={open}
                onOpenChange={(next) => {
                    callbacks.onOpenChange(next);
                    setOpen(next);
                }}
                fit={<Download format="fit" />}
                gpx={<Download format="gpx" />}
                legs={legs}
                savedRouteId={() => Promise.resolve('morning-loop')}
                onSent={(service, routeId) =>
                    callbacks.onSent(service, routeId)
                }
            />
        </div>
    );
}

function SendDialog({
    legs,
    routeServices,
    onOpenChange,
    onSent,
}: {
    legs: DeviceLeg[];
    routeServices: RouteServiceStatus[];
} & Callbacks) {
    callbacks.onOpenChange = onOpenChange;
    callbacks.onSent = onSent;

    return (
        <InertiaPage
            name="saved"
            component={SavedRoute}
            props={{ legs }}
            shared={{
                ...sharedProps({ user: rider, can: null }),
                routeServices,
            }}
        />
    );
}

/**
 * Gets a route onto any brand of device. Where the brand reads routes from a service Traceway can reach, such as
 * Wahoo, it is one tap; everywhere else it is a file and the steps to load it. The dialog remembers the brand the
 * rider picked last.
 */
const meta = {
    title: 'Components/SendToDevice',
    component: SendDialog,
    args: {
        legs: [],
        routeServices: [wahooConnected, rideWithGps],
        onOpenChange: fn(),
        onSent: fn(),
    },
    argTypes: {
        legs: {
            control: 'object',
            description:
                'The legs of a multisport plan, each sent as its own course. Empty for one route.',
        },
        routeServices: {
            control: 'object',
            description:
                'The services Traceway can send routes to, and whether the rider connected each.',
        },
        onOpenChange: { description: 'The dialog opened or closed.' },
        onSent: {
            description:
                'A send went through: the service and the saved route, so the library can show it was sent.',
        },
    },
    parameters: {
        layout: 'fullscreen',
        docs: { story: { inline: false, iframeHeight: 720 } },
    },
} satisfies Meta<typeof SendDialog>;

export default meta;

type Story = StoryObj<typeof meta>;

const dialog = async () => {
    const element = await screen.findByRole('dialog', {
        name: 'Send to device',
    });

    await waitFor(() => expect(element).toBeVisible());

    return within(element);
};

/** No brand picked yet. Picking Garmin shows its steps; closing and opening again remembers it. */
export const PickADevice: Story = {
    beforeEach: pickBrand(null),
    play: async ({ canvas, args, userEvent, step }) => {
        const sheet = await dialog();
        const garmin = sheet.getByRole('button', { name: 'Garmin' });

        await expect(garmin).toHaveAttribute('aria-pressed', 'false');

        await step('Pick Garmin', async () => {
            await userEvent.click(garmin);
            await expect(garmin).toHaveAttribute('aria-pressed', 'true');
            await expect(
                sheet.getByRole('heading', { name: 'FIT course' }),
            ).toBeVisible();
        });

        await step('Escape closes it', async () => {
            await userEvent.keyboard('{Escape}');
            await expect(args.onOpenChange).toHaveBeenCalledWith(false);
            await waitFor(() =>
                expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
            );
        });

        await step('It opens on Garmin again', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Send to device' }),
            );
            const again = await dialog();

            await expect(
                again.getByRole('button', { name: 'Garmin' }),
            ).toHaveAttribute('aria-pressed', 'true');
        });
    },
};

/** Garmin: the FIT course and the two ways to load it. */
export const Garmin: Story = {
    beforeEach: pickBrand('garmin'),
    play: async () => {
        const sheet = await dialog();

        await expect(
            sheet.getByRole('link', { name: 'Download FIT' }),
        ).toBeVisible();
        await expect(
            sheet.getByRole('heading', { name: 'With Garmin Connect' }),
        ).toBeVisible();
        await expect(
            sheet.getByRole('heading', { name: 'Over USB' }),
        ).toBeVisible();
    },
};

/** Wahoo, connected: one tap sends it to the Elemnt, and the dialog says so. */
export const WahooConnected: Story = {
    beforeEach({ msw }) {
        pickBrand('wahoo')();
        msw.use(
            http.post(sendRoute, () =>
                HttpResponse.json({
                    message: 'Sent. It is on your Elemnt at the next sync.',
                    url: 'https://example.com/routes/morning-loop',
                }),
            ),
        );
    },
    play: async ({ args, userEvent }) => {
        const sheet = await dialog();

        await userEvent.click(
            sheet.getByRole('button', { name: 'Send to Wahoo' }),
        );
        await waitFor(() =>
            expect(args.onSent).toHaveBeenCalledWith('wahoo', 'morning-loop'),
        );
        await expect(sheet.getByRole('status')).toHaveTextContent(
            'Sent. It is on your Elemnt at the next sync.',
        );
        await expect(
            sheet.getByRole('link', { name: 'Open in Wahoo' }),
        ).toHaveAttribute('href', 'https://example.com/routes/morning-loop');
        await expect(
            sheet.getByRole('button', { name: 'Send to Wahoo again' }),
        ).toBeEnabled();
    },
};

/** Too many sends: the reason, in words, where the result goes. */
export const SendRefused: Story = {
    beforeEach({ msw }) {
        pickBrand('wahoo')();
        msw.use(
            http.post(sendRoute, () =>
                HttpResponse.json({ message: 'Too many' }, { status: 429 }),
            ),
        );
    },
    play: async ({ args, userEvent }) => {
        const sheet = await dialog();

        await userEvent.click(
            sheet.getByRole('button', { name: 'Send to Wahoo' }),
        );
        await waitFor(() =>
            expect(sheet.getByRole('status')).toHaveTextContent(
                'That was a lot of sends. Try again in a minute.',
            ),
        );
        await expect(args.onSent).not.toHaveBeenCalled();
    },
};

/** Wahoo before the rider connects it. */
export const WahooNotConnected: Story = {
    beforeEach: pickBrand('wahoo'),
    args: {
        routeServices: [
            {
                ...wahooConnected,
                connected: false,
                connected_at: null,
                last_sent_at: null,
            },
        ],
    },
    play: async () => {
        const sheet = await dialog();

        await expect(
            sheet.getByText(/Connect your Wahoo account once/),
        ).toBeVisible();
        await expect(
            sheet.getByRole('button', { name: 'Connect Wahoo' }),
        ).toBeEnabled();
    },
};

/** A brick goes to the device as one course per leg. */
export const MultisportLegs: Story = {
    beforeEach: pickBrand('wahoo'),
    args: {
        legs: [
            { n: 1, label: 'Road' },
            { n: 2, label: 'Run' },
        ],
    },
    play: async () => {
        const sheet = await dialog();

        await expect(
            sheet.getByRole('button', { name: 'Send leg 1, Road' }),
        ).toBeVisible();
        await expect(
            sheet.getByRole('button', { name: 'Send leg 2, Run' }),
        ).toBeVisible();
    },
};
