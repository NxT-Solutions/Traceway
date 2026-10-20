import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import FinishSignUpConfirm from '@/pages/auth/finish-sign-up-confirm';
import Login from '@/pages/auth/login';
import { dashboard, login } from '@/routes';
import { open as openAccount } from '@/routes/connections/finish';
import { guestAuth, rider } from '../../support/fixtures';
import { expectNextPage, expectToast, type Play } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { http, inertiaPage, network, redirect } from '../../support/server';
import { noOptions, services } from './sign-in-fixtures';

type Props = ComponentProps<typeof FinishSignUpConfirm>;

/**
 * Where the link in the sign-up email lands (`/login/finish/{token}`). Opening it changes nothing; the button
 * opens the account. Only the browser that asked for the link connects the service.
 *
 * The flow stories post the button to a mocked server (Mock Service Worker) that answers as SignUpLinkController
 * does: a 302 to `/dashboard` (with an info toast when the service waits for Settings), or a 302 to `/login` with
 * the `connection` error when the link was used.
 */
const meta = {
    title: 'Pages/Auth/Finish sign up confirm',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const token = 'b81e4c0d2a9f47c3';

function page(props: Props) {
    return () => (
        <InertiaPage
            name="auth/finish-sign-up-confirm"
            component={FinishSignUpConfirm}
            props={props}
            shared={sharedProps(guestAuth)}
            url={`/login/finish/${token}`}
            pages={{ 'auth/login': Login }}
        />
    );
}

const sameBrowser: Props = {
    service: services.openStreetMap,
    account: 'samrivera',
    name: 'Sam Rivera',
    email: 'sam@example.com',
    token,
    connects: true,
};

const otherBrowser: Props = { ...sameBrowser, account: null, connects: false };

/** Opened in the browser that started it: OpenStreetMap connects too. */
export const SameBrowser: Story = { render: page(sameBrowser) };

/** Opened somewhere else, such as on a phone: the account opens, and the service waits for Settings. */
export const OtherBrowser: Story = { render: page(otherBrowser) };

/** How many times the mocked server got the button. */
const openRequest = fn();

/** The planner, signed in as Sam, with what the controller flashed. */
function planner(toast?: string) {
    return http.get(dashboard.url(), () =>
        inertiaPage(
            'dashboard',
            {},
            {
                url: dashboard.url(),
                shared: sharedProps({ user: rider, can: null }),
                flash: toast ? { toast: { type: 'info', message: toast } } : {},
            },
        ),
    );
}

async function createAccount({ canvas, userEvent }: Play) {
    await userEvent.click(
        canvas.getByRole('button', { name: 'Create account' }),
    );
}

/** In the browser that started it, the account opens with OpenStreetMap connected, and Sam is in. */
export const CreatesAccount: Story = {
    render: page(sameBrowser),
    beforeEach({ msw }) {
        openRequest.mockClear();
        msw.use(
            http.post(openAccount.url(token), async () => {
                openRequest();
                await network();

                return redirect(dashboard.url());
            }),
            planner(),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Create the account', async () => {
            await createAccount(context);
        });

        await step('The planner opens', async () => {
            await expectNextPage(canvas, 'dashboard');
            await expect(openRequest).toHaveBeenCalledOnce();
        });
    },
};

/** On another device, the account opens without the service, and a toast says where to connect it. */
export const CreatesAccountElsewhere: Story = {
    render: page(otherBrowser),
    beforeEach({ msw }) {
        msw.use(
            http.post(openAccount.url(token), async () => {
                await network();

                return redirect(dashboard.url());
            }),
            planner(
                'Your account is open. To log in with OpenStreetMap, connect it in Settings, under Connections.',
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Create the account', async () => {
            await createAccount(context);
        });

        await step('The planner opens, with a toast', async () => {
            await expectNextPage(canvas, 'dashboard');
            await expectToast(
                canvas,
                'Your account is open. To log in with OpenStreetMap, connect it in Settings, under Connections.',
            );
        });
    },
};

/** The link was used already: the log in page says so. */
export const LinkUsed: Story = {
    render: page(sameBrowser),
    beforeEach({ msw }) {
        msw.use(
            http.post(openAccount.url(token), async () => {
                await network();

                return redirect(login.url());
            }),
            http.get(login.url(), () =>
                inertiaPage(
                    'auth/login',
                    {
                        canResetPassword: true,
                        demoRiders: [],
                        connections: noOptions,
                        pendingConnection: null,
                    },
                    {
                        url: login.url(),
                        shared: {
                            ...sharedProps(guestAuth),
                            errors: {
                                connection:
                                    'That link has expired or was used. Choose the account again to sign up.',
                            },
                        },
                    },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Create the account', async () => {
            await createAccount(context);
        });

        await step('The log in page says the link was used', async () => {
            await expect(
                await canvas.findByText(
                    'That link has expired or was used. Choose the account again to sign up.',
                ),
            ).toBeVisible();
        });
    },
};
