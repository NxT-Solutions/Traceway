import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import VerifyEmail from '@/pages/auth/verify-email';
import { notice, send } from '@/routes/verification';
import { rider } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { http, inertiaPage, network, redirect } from '../../support/server';

type Props = ComponentProps<typeof VerifyEmail>;

/**
 * A signed-in rider who has not opened the confirmation link yet (`/email/verify`).
 *
 * `ResendsLink` posts to a mocked server (Mock Service Worker) that answers as Fortify's
 * EmailVerificationNotificationController does: `back()` with the `verification-link-sent` status, which the page
 * turns into its note.
 */
const meta = {
    title: 'Pages/Auth/Verify email',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const unverified = sharedProps({
    user: { ...rider, email_verified_at: null },
    can: null,
});

function page(props: Props) {
    return () => (
        <InertiaPage
            name="auth/verify-email"
            component={VerifyEmail}
            props={props}
            shared={unverified}
            url={notice.url()}
        />
    );
}

export const Default: Story = { render: page({}) };

/** The rider asked for the link again. */
export const LinkSent: Story = {
    render: page({ status: 'verification-link-sent' }),
};

/** How many times the mocked server sent the link. */
const sendLink = fn();

/** Sam asks for the link again, and the page says a new one went out. */
export const ResendsLink: Story = {
    render: page({}),
    beforeEach({ msw }) {
        sendLink.mockClear();
        msw.use(
            http.post(send.url(), async () => {
                sendLink();
                await network();

                return redirect(notice.url());
            }),
            http.get(notice.url(), () =>
                inertiaPage(
                    'auth/verify-email',
                    { status: 'verification-link-sent' },
                    { url: notice.url(), shared: unverified },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Ask for the link again', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Resend verification email',
                }),
            );
        });

        await step('The page says a new link went out', async () => {
            await expect(
                await canvas.findByText(
                    /A new verification link has been sent/,
                ),
            ).toBeVisible();
            await expect(sendLink).toHaveBeenCalledOnce();
        });
    },
};
