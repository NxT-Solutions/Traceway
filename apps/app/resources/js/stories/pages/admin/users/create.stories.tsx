import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import CreateUser from '@/pages/admin/users/create';
import ShowUser from '@/pages/admin/users/show';
import { create, show, store } from '@/routes/admin/users';
import { lotte, roles } from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { expectToast, type Play } from '../../../support/flows';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    redirect,
    sentData,
    validationErrors,
} from '../../../support/server';
import { followLinks } from '../../../support/story-links';

/**
 * Add a rider, at `/admin/users/create`. AdminUsersController renders it with no props of its own.
 *
 * The flow stories post the form to a mocked server (Mock Service Worker) that answers as StoreUserRequest and
 * AdminUsersController@store do: a taken email is a 302 back with the error under it; a new rider is a 302 to their
 * page with the "Account opened" toast. "← Riders" and Cancel open the Index story.
 */
const meta = {
    title: 'Pages/Admin/Users/Create',
    component: CreateUser,
    parameters: { layout: 'fullscreen' },
    decorators: [
        followLinks({ '/admin/users': ['Pages/Admin/Users/Index', 'Default'] }),
    ],
    render: () => (
        <InertiaPage<Record<string, never>>
            name="admin/users/create"
            component={CreateUser}
            url={create.url()}
            props={{}}
            shared={sharedProps(adminAuth)}
            pages={{ 'admin/users/show': ShowUser }}
        />
    ),
} satisfies Meta<typeof CreateUser>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** What the mocked server got from the form. */
const storeRequest = fn();

/** AdminUsersController@store: records what it got, then answers. */
function storeRoute(answer: () => Response) {
    return http.post(store.url(), async ({ request }) => {
        storeRequest(await sentData(request));
        await network();

        return answer();
    });
}

async function addRider({ canvas, userEvent }: Play, email: string) {
    await userEvent.type(canvas.getByLabelText('Name'), 'Sam Rivera');
    await userEvent.type(canvas.getByLabelText('Email'), email);
    await userEvent.click(
        canvas.getByRole('button', { name: 'Add and send the link' }),
    );
}

/** The email already has an account: the error shows under it, and the field is marked invalid. */
export const EmailTaken: Story = {
    beforeEach({ msw }) {
        storeRequest.mockClear();
        msw.use(
            storeRoute(() => redirect(create.url())),
            http.get(create.url(), () =>
                validationErrors(
                    'admin/users/create',
                    {},
                    { email: 'The email has already been taken.' },
                    { url: create.url(), shared: sharedProps(adminAuth) },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Add Sam with an email that has an account', async () => {
            await addRider(context, 'sam@example.com');
        });

        await step('The error shows under the email', async () => {
            await expect(
                await canvas.findByText('The email has already been taken.'),
            ).toBeVisible();
            await expect(canvas.getByLabelText('Email')).toHaveAttribute(
                'aria-invalid',
                'true',
            );
            await expect(
                canvas.getByLabelText('Email'),
            ).toHaveAccessibleDescription('The email has already been taken.');
        });
    },
};

/** A new rider: their page opens, with a toast that the link to choose a password went out. */
export const AddsRider: Story = {
    beforeEach({ msw }) {
        storeRequest.mockClear();

        const sam = {
            ...lotte,
            id: 12,
            name: 'Sam Rivera',
            email: 'sam.rivera@example.org',
            two_factor: false,
            passkeys: 0,
            routes: 0,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            email_verified_at: new Date().toISOString(),
            last_seen_at: null,
            watches: [],
            apps: [],
            services: [],
            sessions: [],
        };

        msw.use(
            storeRoute(() => redirect(show.url(sam.id))),
            http.get(show.url(sam.id), () =>
                inertiaPage(
                    'admin/users/show',
                    {
                        user: sam,
                        activity: [],
                        roles,
                        can: {
                            manage: true,
                            assignRole: true,
                            self: false,
                            impersonate: true,
                        },
                    },
                    {
                        url: show.url(sam.id),
                        shared: sharedProps(adminAuth),
                        flash: {
                            toast: {
                                type: 'success',
                                message:
                                    'Account opened. sam.rivera@example.org got a link to choose a password.',
                            },
                        },
                    },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Add Sam Rivera', async () => {
            await addRider(context, 'sam.rivera@example.org');
        });

        await step("Sam's page opens, with a toast", async () => {
            await expectToast(
                canvas,
                'Account opened. sam.rivera@example.org got a link to choose a password.',
            );
            await expect(
                canvas.getByRole('heading', { name: 'Sam Rivera' }),
            ).toBeVisible();
            await expect(storeRequest).toHaveBeenCalledWith({
                name: 'Sam Rivera',
                email: 'sam.rivera@example.org',
            });
        });
    },
};
