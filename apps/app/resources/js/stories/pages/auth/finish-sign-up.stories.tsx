import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import FinishSignUp from '@/pages/auth/finish-sign-up';
import Login from '@/pages/auth/login';
import { login } from '@/routes';
import { cancel, existing, store } from '@/routes/connections/finish';
import { guestAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    redirect,
    sentData,
    validationErrors,
} from '../../support/server';
import { noOptions, services } from './sign-in-fixtures';

type Props = ComponentProps<typeof FinishSignUp>;

/**
 * The last step for a new rider whose service gave no email, or one it did not confirm (`/login/finish`).
 * Traceway emails a link, and the account opens when the rider uses it. A rider who already has an account logs
 * in to connect the service instead.
 *
 * The flow stories send the page's three actions to a mocked server (Mock Service Worker) that answers as
 * FinishSignUpController does: "Email me a link" is a 302 to `/login` with the "Check your email" status (or back
 * with FinishSignUpRequest's errors); "Log in to connect" is a 302 to `/login`, where the service waits to connect;
 * Cancel is a 303 to `/login`.
 */
const meta = {
    title: 'Pages/Auth/Finish sign up',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

function page(props: Props) {
    return () => (
        <InertiaPage
            name="auth/finish-sign-up"
            component={FinishSignUp}
            props={props}
            shared={sharedProps(guestAuth)}
            url="/login/finish"
            pages={{ 'auth/login': Login }}
        />
    );
}

const noEmail: Props = {
    service: services.openStreetMap,
    name: 'Sam Rivera',
    email: '',
    turnstileSiteKey: null,
};

/** OpenStreetMap shares a name but no email, so the email field takes focus. */
export const NoEmailShared: Story = { render: page(noEmail) };

/** Discord shared an email it did not confirm. The rider checks it. */
export const EmailToCheck: Story = {
    render: page({
        service: services.discord,
        name: 'Sam Rivera',
        email: 'sam@example.com',
        turnstileSiteKey: null,
    }),
};

/** The log in page the actions lead to, with `overrides` such as a status or a service waiting. */
function loginPage(overrides: Partial<ComponentProps<typeof Login>> = {}) {
    return http.get(login.url(), () =>
        inertiaPage(
            'auth/login',
            {
                canResetPassword: true,
                demoRiders: [],
                connections: noOptions,
                pendingConnection: null,
                ...overrides,
            },
            { url: login.url(), shared: sharedProps(guestAuth) },
        ),
    );
}

/** What the mocked server got from the form. */
const finishRequest = fn();

/** The email Sam typed is not one, so FinishSignUpRequest sends the form back with the error. */
export const InvalidEmail: Story = {
    render: page(noEmail),
    beforeEach({ msw }) {
        finishRequest.mockClear();
        msw.use(
            http.post(store.url(), async ({ request }) => {
                finishRequest(await sentData(request));
                await network();

                return redirect('/login/finish');
            }),
            http.get('/login/finish', () =>
                validationErrors('auth/finish-sign-up', noEmail, {
                    email: 'The email field must be a valid email address.',
                }),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type an address with a typo', async () => {
            await userEvent.type(
                canvas.getByLabelText('Email address'),
                'sam@example',
            );
            await userEvent.click(
                canvas.getByRole('button', { name: 'Email me a link' }),
            );
        });

        await step('The error shows under the email', async () => {
            await expect(await canvas.findByRole('alert')).toHaveTextContent(
                'The email field must be a valid email address.',
            );
            await expect(
                canvas.getByLabelText('Email address'),
            ).toHaveAttribute('aria-invalid', 'true');
        });
    },
};

/** Sam gives an email, and the log in page says the link is on its way. */
export const SendsLink: Story = {
    render: page(noEmail),
    beforeEach({ msw }) {
        finishRequest.mockClear();
        msw.use(
            http.post(store.url(), async ({ request }) => {
                finishRequest(await sentData(request));
                await network();

                return redirect(login.url());
            }),
            loginPage({
                status: 'Check your email. A link to finish your account is on its way to sam@example.com.',
            }),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type the email and ask for the link', async () => {
            await userEvent.type(
                canvas.getByLabelText('Email address'),
                'sam@example.com',
            );
            await userEvent.click(
                canvas.getByRole('button', { name: 'Email me a link' }),
            );
        });

        await step('The log in page says to check the email', async () => {
            await expect(
                await canvas.findByText(
                    'Check your email. A link to finish your account is on its way to sam@example.com.',
                ),
            ).toBeVisible();
            await expect(finishRequest).toHaveBeenCalledWith({
                name: 'Sam Rivera',
                email: 'sam@example.com',
            });
        });
    },
};

/** Sam already has an account: the log in page opens, with OpenStreetMap waiting to connect once they are in. */
export const LogsInToConnect: Story = {
    render: page(noEmail),
    beforeEach({ msw }) {
        msw.use(
            http.post(existing.url(), async () => {
                await network();

                return redirect(login.url());
            }),
            loginPage({
                pendingConnection: {
                    slug: 'openstreetmap',
                    label: 'OpenStreetMap',
                    email: null,
                    confirm: true,
                },
            }),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Choose to log in instead', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Log in to connect OpenStreetMap',
                }),
            );
        });

        await step('The log in page says OpenStreetMap waits', async () => {
            await expect(
                await canvas.findByRole('button', { name: 'Log in' }),
            ).toBeVisible();
            await expect(
                canvas.getAllByText(/OpenStreetMap/).length,
            ).toBeGreaterThan(0);
        });
    },
};

/** Sam changes their mind: the sign-up is dropped, and the log in page opens. */
export const Cancels: Story = {
    render: page(noEmail),
    beforeEach({ msw }) {
        msw.use(
            http.delete(cancel.url(), async () => {
                await network();

                return redirect(login.url(), 303);
            }),
            loginPage(),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Cancel signing up', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Cancel signing up with OpenStreetMap',
                }),
            );
        });

        await step('The log in page opens', async () => {
            await expect(
                await canvas.findByRole('button', { name: 'Log in' }),
            ).toBeVisible();
        });
    },
};
