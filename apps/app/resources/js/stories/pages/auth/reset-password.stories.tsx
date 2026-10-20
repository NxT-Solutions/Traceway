import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import Login from '@/pages/auth/login';
import ResetPassword from '@/pages/auth/reset-password';
import { login } from '@/routes';
import { reset, update } from '@/routes/password';
import { guestAuth } from '../../support/fixtures';
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
import { noOptions, passwordRules } from './sign-in-fixtures';

type Props = ComponentProps<typeof ResetPassword>;

/**
 * Where the reset link lands (`/reset-password/{token}`). The email comes from the link and cannot change.
 *
 * The flow stories post the form to a mocked server (Mock Service Worker) that answers as Fortify's
 * NewPasswordController does: a password that does not match its confirmation is a 302 back with the error; a
 * used link is a 302 back with `passwords.token` on the email; a good one is a 302 to `/login` with the
 * `passwords.reset` status.
 */
const meta = {
    title: 'Pages/Auth/Reset password',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const props: Props = {
    token: 'a3f9c2e1b7d84f6e',
    email: 'sam@example.com',
    passwordRules,
};

const linkUrl = reset.url(props.token, { query: { email: props.email } });

const render = () => (
    <InertiaPage
        name="auth/reset-password"
        component={ResetPassword}
        props={props}
        shared={sharedProps(guestAuth)}
        url={linkUrl}
        pages={{ 'auth/login': Login }}
    />
);

export const Default: Story = { render };

/** What the mocked server got from the form. */
const resetRequest = fn();

/** Fortify's reset route: records what it got, waits like a request, then answers. */
function resetRoute(answer: () => Response) {
    return http.post(update.url(), async ({ request }) => {
        resetRequest(await sentData(request));
        await network();

        return answer();
    });
}

/** The same page again, with `errors`, as `back()->withErrors()` leaves it. */
function backWithErrors(errors: Record<string, string>) {
    return http.get(reset.url(props.token), () =>
        validationErrors('auth/reset-password', props, errors, {
            url: linkUrl,
            shared: sharedProps(guestAuth),
        }),
    );
}

async function choosePassword(
    { canvas, userEvent }: Play,
    password: string,
    confirmation: string,
) {
    await userEvent.type(canvas.getByLabelText('Password'), password);
    await userEvent.type(
        canvas.getByLabelText('Confirm password'),
        confirmation,
    );
    await userEvent.click(
        canvas.getByRole('button', { name: 'Reset password' }),
    );
}

/** The two passwords differ, and the error says so under the password. */
export const PasswordsDiffer: Story = {
    render,
    beforeEach({ msw }) {
        resetRequest.mockClear();
        msw.use(
            resetRoute(() => redirect(linkUrl)),
            backWithErrors({
                password: 'The password field confirmation does not match.',
            }),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Type two different passwords', async () => {
            await choosePassword(
                context,
                'Morning-loop-2026',
                'Morning-loop-2025',
            );
        });

        await step('The error shows under the password', async () => {
            await expect(
                await canvas.findByText(
                    'The password field confirmation does not match.',
                ),
            ).toBeVisible();
        });
    },
};

/** The link was used already, so the reset token no longer counts. */
export const LinkUsed: Story = {
    render,
    beforeEach({ msw }) {
        resetRequest.mockClear();
        msw.use(
            resetRoute(() => redirect(linkUrl)),
            backWithErrors({ email: 'This password reset token is invalid.' }),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Choose a new password', async () => {
            await choosePassword(
                context,
                'Morning-loop-2026',
                'Morning-loop-2026',
            );
        });

        await step('The error shows under the email', async () => {
            await expect(
                await canvas.findByText(
                    'This password reset token is invalid.',
                ),
            ).toBeVisible();
        });
    },
};

/** A good new password: Sam is sent to log in, and the page says the password changed. */
export const ResetsPassword: Story = {
    render,
    beforeEach({ msw }) {
        resetRequest.mockClear();
        msw.use(
            resetRoute(() => redirect(login.url())),
            http.get(login.url(), () =>
                inertiaPage(
                    'auth/login',
                    {
                        canResetPassword: true,
                        demoRiders: [],
                        connections: noOptions,
                        pendingConnection: null,
                        status: 'Your password has been reset.',
                    },
                    { url: login.url(), shared: sharedProps(guestAuth) },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Choose a new password', async () => {
            await choosePassword(
                context,
                'Morning-loop-2026',
                'Morning-loop-2026',
            );
        });

        await step('The log in page says the password changed', async () => {
            await expect(
                await canvas.findByText('Your password has been reset.'),
            ).toBeVisible();
            await expect(resetRequest).toHaveBeenCalledWith(
                expect.objectContaining({
                    token: props.token,
                    email: props.email,
                    password: 'Morning-loop-2026',
                }),
            );
        });
    },
};
