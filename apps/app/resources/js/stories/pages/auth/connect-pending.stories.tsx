import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import ConnectPending from '@/pages/auth/connect-pending';
import { dashboard } from '@/routes';
import { destroy, store } from '@/routes/connections/pending';
import { rider } from '../../support/fixtures';
import { expectNextPage, expectToast } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { http, inertiaPage, network, redirect } from '../../support/server';
import { services } from './sign-in-fixtures';

type Props = ComponentProps<typeof ConnectPending>;

/**
 * Right after logging in, the rider says yes to connecting the service they came from (`/login/connection`). It
 * names both accounts, so a service someone else left waiting on a shared computer is easy to turn down.
 *
 * The flow stories send both buttons to a mocked server (Mock Service Worker) that answers as
 * PendingConnectionController does: Connect is a 302 to `/dashboard` with the "connected" toast; Not now is a 303
 * to `/dashboard` with nothing connected.
 */
const meta = {
    title: 'Pages/Auth/Connect pending',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

function page(props: Props) {
    return () => (
        <InertiaPage
            name="auth/connect-pending"
            component={ConnectPending}
            props={props}
            shared={sharedProps()}
            url="/login/connection"
        />
    );
}

const openStreetMap: Props = {
    service: services.openStreetMap,
    account: 'samrivera',
    email: rider.email,
};

export const Default: Story = { render: page(openStreetMap) };

/** The service shared no name for the account. */
export const NoAccountName: Story = {
    render: page({
        service: services.discord,
        account: null,
        email: rider.email,
    }),
};

/** The planner, with what the controller flashed. */
function planner(toast?: string) {
    return http.get(dashboard.url(), () =>
        inertiaPage(
            'dashboard',
            {},
            {
                url: dashboard.url(),
                flash: toast
                    ? { toast: { type: 'success', message: toast } }
                    : {},
            },
        ),
    );
}

/** Sam says yes: OpenStreetMap connects, and the planner opens with a toast. */
export const Connects: Story = {
    render: page(openStreetMap),
    beforeEach({ msw }) {
        msw.use(
            http.post(store.url(), async () => {
                await network();

                return redirect(dashboard.url());
            }),
            planner(
                'OpenStreetMap connected. Next time you can log in with it.',
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Connect OpenStreetMap', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Connect OpenStreetMap' }),
            );
        });

        await step('The planner opens, with a toast', async () => {
            await expectNextPage(canvas, 'dashboard');
            await expectToast(
                canvas,
                'OpenStreetMap connected. Next time you can log in with it.',
            );
        });
    },
};

/** Not Sam's OpenStreetMap: Not now drops it, and the planner opens. */
export const Declines: Story = {
    render: page(openStreetMap),
    beforeEach({ msw }) {
        msw.use(
            http.delete(destroy.url(), async () => {
                await network();

                return redirect(dashboard.url(), 303);
            }),
            planner(),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Choose Not now', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: /^Not now/,
                }),
            );
        });

        await step('The planner opens', async () => {
            await expectNextPage(canvas, 'dashboard');
        });
    },
};
