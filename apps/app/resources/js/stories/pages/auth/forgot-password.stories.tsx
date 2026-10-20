import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import ForgotPassword from '@/pages/auth/forgot-password';
import {
    request as forgotPassword,
    email as sendLink,
} from '@/routes/password';
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
import { followLinks } from '../../support/story-links';

type Props = ComponentProps<typeof ForgotPassword>;

/**
 * Asks for the email to send a password reset link to (`/forgot-password`).
 *
 * The flow stories post the form to a mocked server (Mock Service Worker) that answers as Fortify's
 * PasswordResetLinkController does: `back()` with the `passwords.sent` status when the link went out, or with the
 * `passwords.user` error under the field. "log in" opens the Login story.
 */
const meta = {
    title: 'Pages/Auth/Forgot password',
    parameters: { layout: 'fullscreen' },
    decorators: [followLinks({ '/login': ['Pages/Auth/Login', 'Default'] })],
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

function page(props: Props) {
    return () => (
        <InertiaPage
            name="auth/forgot-password"
            component={ForgotPassword}
            props={props}
            shared={sharedProps(guestAuth)}
            url="/forgot-password"
        />
    );
}

export const Default: Story = { render: page({ turnstileSiteKey: null }) };

/** The link went out. */
export const LinkSent: Story = {
    render: page({
        turnstileSiteKey: null,
        status: 'We have emailed your password reset link.',
    }),
};

/** What the mocked server got from the form. */
const linkRequest = fn();

/** Fortify's reset link route: records the email, waits like a request, then sends the rider back. */
const linkRoute = http.post(sendLink.url(), async ({ request }) => {
    linkRequest(await sentData(request));
    await network();

    return redirect(forgotPassword.url());
});

async function askForLink({ canvas, userEvent }: Play, address: string) {
    await userEvent.type(canvas.getByLabelText('Email address'), address);
    await userEvent.click(
        canvas.getByRole('button', { name: 'Email password reset link' }),
    );
}

/** Sam asks for a link, and the page says it went out. */
export const SendsLink: Story = {
    render: page({ turnstileSiteKey: null }),
    beforeEach({ msw }) {
        linkRequest.mockClear();
        msw.use(
            linkRoute,
            http.get(forgotPassword.url(), () =>
                inertiaPage(
                    'auth/forgot-password',
                    {
                        turnstileSiteKey: null,
                        status: 'We have emailed your password reset link.',
                    },
                    {
                        url: forgotPassword.url(),
                        shared: sharedProps(guestAuth),
                    },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Ask for a link for sam@example.com', async () => {
            await askForLink(context, 'sam@example.com');
        });

        await step('The page says the link went out', async () => {
            await expect(
                await canvas.findByText(
                    'We have emailed your password reset link.',
                ),
            ).toBeVisible();
            await expect(linkRequest).toHaveBeenCalledWith(
                expect.objectContaining({ email: 'sam@example.com' }),
            );
        });
    },
};

/** No account has that email, and the error says so under the field. */
export const UnknownEmail: Story = {
    render: page({ turnstileSiteKey: null }),
    beforeEach({ msw }) {
        linkRequest.mockClear();
        msw.use(
            linkRoute,
            http.get(forgotPassword.url(), () =>
                validationErrors(
                    'auth/forgot-password',
                    { turnstileSiteKey: null },
                    { email: "We can't find a user with that email address." },
                    {
                        url: forgotPassword.url(),
                        shared: sharedProps(guestAuth),
                    },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Ask for a link for an unknown email', async () => {
            await askForLink(context, 'sam.rivera@example.org');
        });

        await step('The error shows under the field', async () => {
            await expect(
                await canvas.findByText(
                    "We can't find a user with that email address.",
                ),
            ).toBeVisible();
        });
    },
};
