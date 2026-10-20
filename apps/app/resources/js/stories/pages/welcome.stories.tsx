import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import Login from '@/pages/auth/login';
import Welcome from '@/pages/welcome';
import { login } from '@/routes';
import { guestAuth } from '../support/fixtures';
import { InertiaPage, sharedProps } from '../support/inertia-page';
import { http, inertiaPage, network } from '../support/server';
import { followLinks } from '../support/story-links';
import { noOptions } from './auth/sign-in-fixtures';

/**
 * The planner's own home page (`/`, no layout). A guest gets Log in and Start a route; a signed-in rider goes
 * straight to their routes. The page takes no props; it reads who is signed in from the shared props.
 *
 * Log in, Start a route and Your routes open those pages' stories. `GuestOpensLogIn` follows Log in the way the app
 * does instead: an Inertia visit, answered by a mocked server (Mock Service Worker) with Fortify's login view.
 */
const meta = {
    title: 'Pages/Welcome',
    parameters: { layout: 'fullscreen' },
    decorators: [
        followLinks({
            '/login': ['Pages/Auth/Login', 'Default'],
            '/register': ['Pages/Auth/Register', 'Default'],
            '/dashboard': ['Pages/Planner/Dashboard', 'Empty'],
        }),
    ],
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

export const Guest: Story = {
    render: () => (
        <InertiaPage
            name="welcome"
            component={Welcome}
            props={{}}
            shared={sharedProps(guestAuth)}
            pages={{ 'auth/login': Login }}
        />
    ),
};

export const SignedIn: Story = {
    render: () => (
        <InertiaPage
            name="welcome"
            component={Welcome}
            props={{}}
            shared={sharedProps()}
        />
    ),
};

/** A guest follows Log in, and the log in page opens in place, ready for an email. */
export const GuestOpensLogIn: Story = {
    render: Guest.render,
    parameters: { followLinks: false },
    beforeEach({ msw }) {
        msw.use(
            http.get(login.url(), async () => {
                await network();

                return inertiaPage(
                    'auth/login',
                    {
                        canResetPassword: true,
                        demoRiders: [],
                        connections: noOptions,
                        pendingConnection: null,
                    },
                    { url: login.url(), shared: sharedProps(guestAuth) },
                );
            }),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Follow Log in', async () => {
            await userEvent.click(canvas.getByRole('link', { name: 'Log in' }));
        });

        await step('The log in page opens', async () => {
            await expect(
                await canvas.findByLabelText('Email address'),
            ).toBeVisible();
            await expect(
                canvas.getByRole('button', { name: 'Log in' }),
            ).toBeVisible();
        });
    },
};
