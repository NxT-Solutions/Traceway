import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import ConfirmPassword from '@/pages/auth/confirm-password';
import { confirm } from '@/routes/password';
import { store } from '@/routes/password/confirm';
import { edit as security } from '@/routes/security';
import { expectNextPage, type Play } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    redirect,
    sentData,
    validationErrors,
} from '../../support/server';
import { services } from './sign-in-fixtures';

type Props = ComponentProps<typeof ConfirmPassword>;

/**
 * Before a sensitive step, such as adding a way to log in, the rider shows it is them (`/user/confirm-password`).
 * A rider with a password gives it; one without uses a passkey or a service they log in with.
 *
 * The flow stories post the password to a mocked server (Mock Service Worker) that answers as Fortify's
 * ConfirmablePasswordController does: a wrong password is a 302 back with the error on `password`; the right one
 * is a 302 to the page the rider was going to (`redirect()->intended()`), here Settings, Security.
 */
const meta = {
    title: 'Pages/Auth/Confirm password',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

function page(props: Props, shared = sharedProps()) {
    return () => (
        <InertiaPage
            name="auth/confirm-password"
            component={ConfirmPassword}
            props={props}
            shared={shared}
            url={confirm.url()}
        />
    );
}

export const WithPassword: Story = {
    render: page({ hasPassword: true, connections: [] }),
};

/** No password: the services the rider logs in with confirm it is them. */
export const WithConnectedServices: Story = {
    render: page({
        hasPassword: false,
        connections: [services.google, services.wahoo],
    }),
};

/** No password and no service: a passkey is the only way, and the page says how to set a password. */
export const PasskeyOnly: Story = {
    render: page({ hasPassword: false, connections: [] }),
};

/** The service answered for a different account. */
export const ConnectionError: Story = {
    render: page(
        { hasPassword: false, connections: [services.google, services.wahoo] },
        {
            ...sharedProps(),
            errors: {
                connection:
                    'That Google account is not the one connected to your Traceway account.',
            },
        },
    ),
};

/** What the mocked server got from the form. */
const confirmRequest = fn();

/** Fortify's confirm route: records what it got, waits like a request, then answers. */
function confirmRoute(answer: () => Response) {
    return http.post(store.url(), async ({ request }) => {
        confirmRequest(await sentData(request));
        await network();

        return answer();
    });
}

async function confirmWith({ canvas, userEvent }: Play, password: string) {
    await userEvent.type(canvas.getByLabelText('Password'), password);
    await userEvent.click(
        canvas.getByRole('button', { name: 'Confirm password' }),
    );
}

/** A wrong password: the error shows under the field. */
export const WrongPassword: Story = {
    render: page({ hasPassword: true, connections: [] }),
    beforeEach({ msw }) {
        confirmRequest.mockClear();
        msw.use(
            confirmRoute(() => redirect(confirm.url())),
            http.get(confirm.url(), () =>
                validationErrors(
                    'auth/confirm-password',
                    { hasPassword: true, connections: [] },
                    { password: 'The provided password was incorrect.' },
                    { url: confirm.url() },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type a wrong password', async () => {
            await confirmWith(context, 'not-my-password');
        });

        await step('The error shows under the password', async () => {
            await expect(
                await canvas.findByText('The provided password was incorrect.'),
            ).toBeVisible();
        });
    },
};

/** The right password: Sam goes on to the page that asked, Settings, Security. */
export const Confirms: Story = {
    render: page({ hasPassword: true, connections: [] }),
    beforeEach({ msw }) {
        confirmRequest.mockClear();
        msw.use(
            confirmRoute(() => redirect(security.url())),
            http.get(security.url(), () =>
                inertiaPage('settings/security', {}, { url: security.url() }),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type the password', async () => {
            await confirmWith(context, 'correct horse battery');
        });

        await step('The button says it is confirming', async () => {
            await expect(
                await canvas.findByRole('button', { name: 'Confirming…' }),
            ).toBeDisabled();
        });

        await step('Settings, Security opens', async () => {
            await expectNextPage(canvas, 'settings/security');
            await expect(confirmRequest).toHaveBeenCalledWith({
                password: 'correct horse battery',
            });
        });
    },
};
