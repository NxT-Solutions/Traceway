import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import TwoFactorChallenge from '@/pages/auth/two-factor-challenge';
import { dashboard } from '@/routes';
import { login as challenge } from '@/routes/two-factor';
import { store } from '@/routes/two-factor/login';
import { guestAuth } from '../../support/fixtures';
import { expectNextPage } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    redirect,
    sentData,
    validationErrors,
} from '../../support/server';

/**
 * After the password, a rider with two-factor sign-in gives the six-digit code from their authenticator app, or
 * switches to a recovery code (`/two-factor-challenge`). The page takes no props; the switch is its own state.
 *
 * The flow stories post the code to a mocked server (Mock Service Worker) that answers as Fortify's
 * TwoFactorAuthenticatedSessionController does: a wrong code is a 302 back to `/two-factor-challenge` with the error
 * on `code` (or `recovery_code`); a right one is a 302 to `/dashboard`.
 */
const meta = {
    title: 'Pages/Auth/Two-factor challenge',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const render = () => (
    <InertiaPage
        name="auth/two-factor-challenge"
        component={TwoFactorChallenge}
        props={{}}
        shared={sharedProps(guestAuth)}
        url={challenge.url()}
    />
);

export const Default: Story = { render };

/** What the mocked server got from the form. */
const challengeRequest = fn();

/** Fortify's challenge route: records what it got, waits like a request, then answers. */
function challengeRoute(answer: () => Response) {
    return http.post(store.url(), async ({ request }) => {
        challengeRequest(await sentData(request));
        await network();

        return answer();
    });
}

const planner = http.get(dashboard.url(), () =>
    inertiaPage('dashboard', {}, { url: dashboard.url() }),
);

/** A code from a minute ago: the error shows, and the boxes clear for the next one. */
export const WrongCode: Story = {
    render,
    beforeEach({ msw }) {
        challengeRequest.mockClear();
        msw.use(
            challengeRoute(() => redirect(challenge.url())),
            http.get(challenge.url(), () =>
                validationErrors(
                    'auth/two-factor-challenge',
                    {},
                    {
                        code: 'The provided two factor authentication code was invalid.',
                    },
                    {
                        url: challenge.url(),
                        shared: sharedProps(guestAuth),
                    },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type an old code and continue', async () => {
            await userEvent.type(canvas.getByRole('textbox'), '418207');
            await userEvent.click(
                canvas.getByRole('button', { name: 'Continue' }),
            );
        });

        await step('The error shows under the code', async () => {
            await expect(
                await canvas.findByText(
                    'The provided two factor authentication code was invalid.',
                ),
            ).toBeVisible();
            await expect(challengeRequest).toHaveBeenCalledWith(
                expect.objectContaining({ code: '418207' }),
            );
        });
    },
};

/** The code from the app: Sam is in, and the planner opens. */
export const SignsInWithCode: Story = {
    render,
    beforeEach({ msw }) {
        challengeRequest.mockClear();
        msw.use(
            challengeRoute(() => redirect(dashboard.url())),
            planner,
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type the code and continue', async () => {
            await userEvent.type(canvas.getByRole('textbox'), '904615');
            await userEvent.click(
                canvas.getByRole('button', { name: 'Continue' }),
            );
        });

        await step('The planner opens', async () => {
            await expectNextPage(canvas, 'dashboard');
            await expect(challengeRequest).toHaveBeenCalledWith(
                expect.objectContaining({ code: '904615' }),
            );
        });
    },
};

/** No phone at hand: Sam switches to a recovery code, and it lets them in. */
export const SignsInWithRecoveryCode: Story = {
    render,
    beforeEach({ msw }) {
        challengeRequest.mockClear();
        msw.use(
            challengeRoute(() => redirect(dashboard.url())),
            planner,
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Switch to a recovery code', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'login using a recovery code',
                }),
            );
            await expect(
                await canvas.findByPlaceholderText('Enter recovery code'),
            ).toBeVisible();
        });

        await step('Type a recovery code and continue', async () => {
            await userEvent.type(
                canvas.getByPlaceholderText('Enter recovery code'),
                'k7Qm2xPd9R-hT4vW8nZc3',
            );
            await userEvent.click(
                canvas.getByRole('button', { name: 'Continue' }),
            );
        });

        await step('The planner opens', async () => {
            await expectNextPage(canvas, 'dashboard');
            await expect(challengeRequest).toHaveBeenCalledWith(
                expect.objectContaining({
                    recovery_code: 'k7Qm2xPd9R-hT4vW8nZc3',
                }),
            );
        });
    },
};
