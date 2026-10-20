import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import Register from '@/pages/auth/register';
import VerifyEmail from '@/pages/auth/verify-email';
import { dashboard } from '@/routes';
import { store } from '@/routes/register';
import { notice } from '@/routes/verification';
import { guestAuth, rider } from '../../support/fixtures';
import type { Play } from '../../support/flows';
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
import { featuredOptions, noOptions, passwordRules } from './sign-in-fixtures';

type Props = ComponentProps<typeof Register>;

/**
 * The sign up page (`/register`). The services open an account without a password; the form below opens one with
 * an email and a password. The person check (Turnstile) is off here, as it is without a site key.
 *
 * The flow stories post the form to a mocked server (Mock Service Worker) that answers as Fortify and
 * CreateNewUser do: a taken email or a short password is a 302 back to `/register` with the errors; a new account
 * is a 302 to `/dashboard`, which the `verified` middleware sends on to `/email/verify` until the rider opens the
 * link. "Log in" opens the Login story.
 */
const meta = {
    title: 'Pages/Auth/Register',
    parameters: { layout: 'fullscreen' },
    decorators: [followLinks({ '/login': ['Pages/Auth/Login', 'Default'] })],
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const props: Props = {
    passwordRules,
    turnstileSiteKey: null,
    connections: featuredOptions,
};

function page(overrides: Partial<Props> = {}) {
    return () => (
        <InertiaPage
            name="auth/register"
            component={Register}
            props={{ ...props, ...overrides }}
            shared={sharedProps(guestAuth)}
            url="/register"
            pages={{ 'auth/verify-email': VerifyEmail }}
        />
    );
}

export const Default: Story = { render: page() };

/** No service is set up: the email form alone. */
export const EmailOnly: Story = { render: page({ connections: noOptions }) };

/** What the mocked server got from the form. */
const registerRequest = fn();

/** Fortify's register route: records what it got, waits like a request, then answers. */
function registerRoute(answer: () => Response) {
    return http.post(store.url(), async ({ request }) => {
        registerRequest(await sentData(request));
        await network();

        return answer();
    });
}

/** Sam fills in the form and creates the account. */
async function signUp({ canvas, userEvent }: Play, password: string) {
    await userEvent.type(canvas.getByLabelText('Name'), 'Sam Rivera');
    await userEvent.type(
        canvas.getByLabelText('Email address'),
        'sam@example.com',
    );
    await userEvent.type(canvas.getByLabelText('Password'), password);
    await userEvent.type(canvas.getByLabelText('Confirm password'), password);
    await userEvent.click(
        canvas.getByRole('button', { name: 'Create account' }),
    );
}

/**
 * The email already has an account, and the password is shorter than `Password::defaults()` allows. Both errors
 * come back under their fields, as Laravel's validator words them.
 */
export const EmailTaken: Story = {
    render: page({ connections: noOptions }),
    beforeEach({ msw }) {
        registerRequest.mockClear();
        msw.use(
            registerRoute(() => redirect(store.url())),
            http.get(store.url(), () =>
                validationErrors(
                    'auth/register',
                    { ...props, connections: noOptions },
                    {
                        email: 'The email has already been taken.',
                        password:
                            'The password field must be at least 12 characters.',
                    },
                    { url: store.url(), shared: sharedProps(guestAuth) },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Fill in the form with a short password', async () => {
            await signUp(context, 'Short1!');
        });

        await step('Both errors show under their fields', async () => {
            await expect(
                await canvas.findByText('The email has already been taken.'),
            ).toBeVisible();
            await expect(
                canvas.getByText(
                    'The password field must be at least 12 characters.',
                ),
            ).toBeVisible();
            await expect(registerRequest).toHaveBeenCalledWith(
                expect.objectContaining({
                    name: 'Sam Rivera',
                    email: 'sam@example.com',
                }),
            );
        });
    },
};

/**
 * A new email and a strong password open the account. The planner wants a confirmed email, so the rider lands on
 * the page that asks them to open the link.
 */
export const CreatesAccount: Story = {
    render: page({ connections: noOptions }),
    beforeEach({ msw }) {
        registerRequest.mockClear();
        msw.use(
            registerRoute(() => redirect(dashboard.url())),
            // The `verified` middleware on the planner.
            http.get(dashboard.url(), () => redirect(notice.url())),
            http.get(notice.url(), () =>
                inertiaPage(
                    'auth/verify-email',
                    {},
                    {
                        url: notice.url(),
                        shared: sharedProps({
                            user: {
                                ...rider,
                                email_verified_at: null,
                                created_at: new Date().toISOString(),
                            },
                            can: null,
                        }),
                    },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Fill in the form', async () => {
            await signUp(context, 'Gravel-loop-2026');
        });

        await step('The page asks Sam to confirm the email', async () => {
            await expect(
                await canvas.findByRole('button', {
                    name: 'Resend verification email',
                }),
            ).toBeVisible();
            await expect(registerRequest).toHaveBeenCalledWith(
                expect.objectContaining({
                    password: 'Gravel-loop-2026',
                    password_confirmation: 'Gravel-loop-2026',
                }),
            );
        });
    },
};
