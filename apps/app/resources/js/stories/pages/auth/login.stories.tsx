import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import Login from '@/pages/auth/login';
import TwoFactorChallenge from '@/pages/auth/two-factor-challenge';
import { dashboard } from '@/routes';
import { store } from '@/routes/login';
import { login as twoFactorChallenge } from '@/routes/two-factor';
import { guestAuth } from '../../support/fixtures';
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
import { followLinks } from '../../support/story-links';
import { featuredOptions, noOptions } from './sign-in-fixtures';

type Props = ComponentProps<typeof Login>;

/**
 * The log in page (`/login`, Fortify's login view). Services a rider already has come first, then a passkey, then
 * email and password. One note at a time sits above the form: why a service did not log the rider in, a service
 * waiting to connect, or other news such as a password reset.
 *
 * The flow stories (`WrongPassword`, `SignsIn`, `SignsInWithTwoFactor`) post the form to a mocked server (Mock Service
 * Worker) that answers as Fortify does: a wrong password is a 302 back to `/login` with the error under the email
 * field; a right one is a 302 to `/dashboard`, or to `/two-factor-challenge` when the rider has two-factor on.
 * "Forgot your password?" and "Sign up" open those pages' stories.
 */
const meta = {
    title: 'Pages/Auth/Login',
    parameters: { layout: 'fullscreen' },
    decorators: [
        followLinks({
            '/register': ['Pages/Auth/Register', 'Default'],
            '/forgot-password': ['Pages/Auth/Forgot password', 'Default'],
        }),
    ],
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const props: Props = {
    canResetPassword: true,
    demoRiders: [],
    connections: featuredOptions,
    pendingConnection: null,
};

function page(overrides: Partial<Props> = {}, shared = sharedProps(guestAuth)) {
    return () => (
        <InertiaPage
            name="auth/login"
            component={Login}
            props={{ ...props, ...overrides }}
            shared={shared}
            url="/login"
            pages={{ 'auth/two-factor-challenge': TwoFactorChallenge }}
        />
    );
}

export const Default: Story = { render: page() };

/** This browser last logged in with Google, so it leads the row and is marked. */
export const LastUsedGoogle: Story = {
    render: page({ connections: { ...featuredOptions, lastUsed: 'google' } }),
};

/** No service is set up on this Traceway: the email form alone. */
export const EmailOnly: Story = { render: page({ connections: noOptions }) };

/** Locally, the example riders log in with one click. */
export const WithDemoRiders: Story = {
    render: page({
        demoRiders: [
            {
                key: 'road',
                name: 'Road',
                description: 'Paved loops on quiet roads.',
                role: 'Super Admin',
            },
            {
                key: 'gravel',
                name: 'Gravel',
                description: 'Mixed roads and dirt.',
                role: 'Admin',
            },
            {
                key: 'run',
                name: 'Run',
                description: 'Paths and pavements.',
                role: 'User',
            },
            {
                key: 'hike',
                name: 'Hike',
                description: 'Trails and a shorter day.',
                role: 'User',
            },
        ],
    }),
};

/** A service sent the rider back without logging them in. The reason takes focus. */
export const ConnectionError: Story = {
    render: page(
        {},
        {
            ...sharedProps(guestAuth),
            errors: {
                connection:
                    'Google did not connect, because it was cancelled there.',
            },
        },
    ),
};

/** OpenStreetMap does not confirm an email, so it waits for the rider to log in and say yes. */
export const PendingConnection: Story = {
    render: page({
        pendingConnection: {
            slug: 'openstreetmap',
            label: 'OpenStreetMap',
            email: null,
            confirm: true,
        },
    }),
};

/** Back from a password reset. */
export const PasswordReset: Story = {
    render: page({ status: 'Your password has been reset.' }),
};

/** What the mocked server got from the form. */
const loginRequest = fn();

/** Fortify's login route: records what it got, waits like a request, then answers. */
function loginRoute(answer: () => Response) {
    return http.post(store.url(), async ({ request }) => {
        loginRequest(await sentData(request));
        await network();

        return answer();
    });
}

/** Sam types the email and a password, and logs in. */
async function logIn({ canvas, userEvent }: Play, password: string) {
    await userEvent.type(
        canvas.getByLabelText('Email address'),
        'sam@example.com',
    );
    await userEvent.type(canvas.getByLabelText('Password'), password);
    await userEvent.click(canvas.getByRole('button', { name: 'Log in' }));
}

/**
 * Sam mistypes the password. Fortify's `auth.failed` comes back on the email field, as Laravel sends it: a 302 back
 * to `/login` with the errors in the session.
 */
export const WrongPassword: Story = {
    render: page({ connections: noOptions }),
    beforeEach({ msw }) {
        loginRequest.mockClear();
        msw.use(
            loginRoute(() => redirect('/login')),
            http.get('/login', () =>
                validationErrors(
                    'auth/login',
                    { ...props, connections: noOptions },
                    { email: 'These credentials do not match our records.' },
                    { url: '/login', shared: sharedProps(guestAuth) },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type the email and a wrong password', async () => {
            await logIn(context, 'not-my-password');
        });

        await step('The error shows under the email', async () => {
            await expect(
                await canvas.findByText(
                    'These credentials do not match our records.',
                ),
            ).toBeVisible();
            await expect(loginRequest).toHaveBeenCalledWith(
                expect.objectContaining({ email: 'sam@example.com' }),
            );
        });

        await step('Sam stays on the page, with the email kept', async () => {
            await expect(canvas.getByLabelText('Email address')).toHaveValue(
                'sam@example.com',
            );
        });
    },
};

/** Sam logs in with the right password, and Fortify sends them on to the planner (`/dashboard`). */
export const SignsIn: Story = {
    render: page({ connections: noOptions }),
    beforeEach({ msw }) {
        loginRequest.mockClear();
        msw.use(
            loginRoute(() => redirect(dashboard.url())),
            http.get(dashboard.url(), () =>
                inertiaPage('dashboard', {}, { url: dashboard.url() }),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type the email and password', async () => {
            await logIn(context, 'correct horse battery');
        });

        await step('The button says it is logging in', async () => {
            await expect(
                await canvas.findByRole('button', { name: 'Logging in…' }),
            ).toBeDisabled();
        });

        await step('The planner opens', async () => {
            await expectNextPage(canvas, 'dashboard');
            await expect(loginRequest).toHaveBeenCalledWith(
                expect.objectContaining({
                    email: 'sam@example.com',
                    password: 'correct horse battery',
                }),
            );
        });
    },
};

/**
 * Sam has two-factor sign-in on, so the right password leads to the code page instead: a 302 to
 * `/two-factor-challenge`, as Fortify's RedirectIfTwoFactorAuthenticatable sends it. The Two-factor challenge
 * stories go on from there.
 */
export const SignsInWithTwoFactor: Story = {
    render: page({ connections: noOptions }),
    beforeEach({ msw }) {
        loginRequest.mockClear();
        msw.use(
            loginRoute(() => redirect(twoFactorChallenge.url())),
            http.get(twoFactorChallenge.url(), () =>
                inertiaPage(
                    'auth/two-factor-challenge',
                    {},
                    {
                        url: twoFactorChallenge.url(),
                        shared: sharedProps(guestAuth),
                    },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type the email and password', async () => {
            await logIn(context, 'correct horse battery');
        });

        await step('The page asks for the authenticator code', async () => {
            await expect(
                await canvas.findByRole('button', {
                    name: 'login using a recovery code',
                }),
            ).toBeVisible();
            await expect(
                canvas.getByText(
                    'Enter the authentication code provided by your authenticator application.',
                ),
            ).toBeVisible();
        });
    },
};
