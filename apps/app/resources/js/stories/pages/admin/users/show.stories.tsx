import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, screen, waitFor, within } from 'storybook/test';
import Users from '@/pages/admin/users/index';
import ShowUser from '@/pages/admin/users/show';
import users, { destroy, index, role, show } from '@/routes/admin/users';
import {
    lotte,
    lotteActivity,
    noFilters,
    roles,
    userPage,
} from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { expectToast, type Play } from '../../../support/flows';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';
import {
    form,
    http,
    inertiaPage,
    network,
    redirect,
    sentData,
} from '../../../support/server';
import { followLinks } from '../../../support/story-links';

type ShowUserProps = ComponentProps<typeof ShowUser>;

/** When a rider on the list joined and was last seen, so their page agrees with the list. */
function seen(id: number) {
    const row = userPage.users.find((user) => user.id === id);

    return {
        created_at: row?.created_at ?? null,
        updated_at: row?.created_at ?? null,
        last_seen_at: row?.last_seen_at ?? null,
        email_verified_at: row?.verified ? (row.created_at ?? null) : null,
    };
}

/**
 * One rider at `/admin/users/{rider}`, with the props AdminUsersController renders: the account, what admins did to
 * it, the roles, and what this admin may do. A write that cannot be undone, or that signs someone out, asks first.
 *
 * The flow stories run the actions against a mocked server (Mock Service Worker) that answers as
 * AdminUsersController does: the page's forms spoof DELETE and PUT as Laravel reads them, and each is a 303 back to
 * the rider with its toast; Delete account is a 303 to the list of riders. "← Riders" opens the Index story.
 */
const meta = {
    title: 'Pages/Admin/Users/Show',
    component: ShowUser,
    parameters: { layout: 'fullscreen' },
    decorators: [
        followLinks({ '/admin/users': ['Pages/Admin/Users/Index', 'Default'] }),
    ],
    args: {
        user: lotte,
        activity: lotteActivity,
        roles,
        can: {
            manage: true,
            assignRole: true,
            self: false,
            impersonate: true,
        },
    },
    render: (args) => (
        <InertiaPage<ShowUserProps>
            name="admin/users/show"
            component={ShowUser}
            url={`/admin/users/${args.user.id}`}
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof ShowUser>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A Super Admin looking at a rider: every action is open. */
export const Rider: Story = {};

/** An admin may look, but not change this account. */
export const ReadOnly: Story = {
    args: {
        can: {
            manage: false,
            assignRole: false,
            self: false,
            impersonate: false,
        },
    },
};

/** An admin's own account: no acting as yourself, no deleting yourself here. */
export const Yourself: Story = {
    args: {
        user: {
            ...lotte,
            ...seen(1),
            id: 1,
            name: adminAuth.user.name,
            email: adminAuth.user.email,
            role: 'super-admin',
            two_factor: true,
            passkeys: 2,
            routes: 48,
            watches: [],
            apps: [],
            services: [],
        },
        activity: [],
        can: {
            manage: false,
            assignRole: false,
            self: true,
            impersonate: false,
        },
    },
};

/** Where Lotte's actions go, and what the mocked server got. */
const lotteUrl = show.url(lotte.id);
const actionRequest = fn();

const lotteProps: ShowUserProps = {
    user: lotte,
    activity: lotteActivity,
    roles,
    can: { manage: true, assignRole: true, self: false, impersonate: true },
};

/** Lotte's page as the server has it after an action, with the toast AdminUsersController flashed. */
function lottePage(overrides: Partial<ShowUserProps>, toast: string) {
    return http.get(lotteUrl, () =>
        inertiaPage(
            'admin/users/show',
            { ...lotteProps, ...overrides },
            {
                url: lotteUrl,
                shared: sharedProps(adminAuth),
                flash: { toast: { type: 'success', message: toast } },
            },
        ),
    );
}

/** A spoofed DELETE or PUT from one of the page's forms: records it, then goes back to Lotte's page. */
function action(definition: { action: string; method: string }) {
    return form(definition, async ({ request }) => {
        actionRequest(
            new URL(request.url).searchParams.get('_method'),
            new URL(request.url).pathname,
            await sentData(request),
        );
        await network();

        return redirect(lotteUrl, 303);
    });
}

/** Opens a Confirm dialog with its button on the page, and says yes. */
async function confirm(
    { canvas, userEvent }: Play,
    button: string,
    yes: string,
) {
    await userEvent.click(canvas.getByRole('button', { name: button }));

    const dialog = await screen.findByRole('dialog');

    // The dialog fades in.
    await waitFor(() => expect(dialog).toBeVisible());
    await userEvent.click(within(dialog).getByRole('button', { name: yes }));
}

/** The admin signs Lotte out everywhere: the dialog asks first, and the activity log gets the new entry. */
export const SignsOutEverywhere: Story = {
    beforeEach({ msw }) {
        actionRequest.mockClear();
        msw.use(
            action(users.sessions.destroy.form(lotte.id)),
            lottePage(
                {
                    activity: [
                        {
                            id: 46,
                            action: 'signed_out',
                            summary: 'Signed out everywhere',
                            at: new Date().toISOString(),
                            actor: { id: 1, name: adminAuth.user.name },
                            rider: { id: lotte.id, name: lotte.name },
                        },
                        ...lotteActivity,
                    ],
                },
                'The rider is signed out everywhere.',
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step('Sign Lotte out everywhere, and say yes', async () => {
            await confirm(
                context,
                'Sign out everywhere',
                'Sign out everywhere',
            );
        });

        await step('The dialog closes, and the log has the entry', async () => {
            await expectToast(canvas, 'The rider is signed out everywhere.');
            await waitFor(() =>
                expect(screen.queryByRole('dialog')).toBeNull(),
            );
            await expect(
                canvas.getAllByText('Signed out everywhere').length,
            ).toBe(2);
            await expect(actionRequest).toHaveBeenCalledWith(
                'DELETE',
                users.sessions.destroy.url(lotte.id),
                {},
            );
        });
    },
};

/** The admin makes Lotte an Admin: her role changes on the page. */
export const ChangesRole: Story = {
    beforeEach({ msw }) {
        actionRequest.mockClear();
        msw.use(
            action(role.form(lotte.id)),
            lottePage(
                { user: { ...lotte, role: 'admin' } },
                'The rider is now Admin.',
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Pick Admin and give the role', async () => {
            await userEvent.selectOptions(
                canvas.getByRole('combobox', { name: 'Role' }),
                'admin',
            );
            await userEvent.click(
                canvas.getByRole('button', { name: 'Give this role' }),
            );
        });

        await step('Lotte is an Admin now', async () => {
            await expectToast(canvas, 'The rider is now Admin.');
            await expect(
                within(
                    canvas.getByRole('heading', { name: lotte.name })
                        .parentElement as HTMLElement,
                ).getByText('Admin'),
            ).toBeVisible();
            await expect(actionRequest).toHaveBeenCalledWith(
                'PUT',
                role.url(lotte.id),
                { role: 'admin' },
            );
        });
    },
};

/** The admin deletes Lotte's account: the list of riders opens without her, with a toast. */
export const DeletesAccount: Story = {
    render: (args) => (
        <InertiaPage<ShowUserProps>
            name="admin/users/show"
            component={ShowUser}
            url={show.url(args.user.id)}
            props={args}
            shared={sharedProps(adminAuth)}
            pages={{ 'admin/users/index': Users }}
        />
    ),
    beforeEach({ msw }) {
        actionRequest.mockClear();

        const left = userPage.users.filter((user) => user.id !== lotte.id);

        msw.use(
            form(destroy.form(lotte.id), async ({ request }) => {
                actionRequest(
                    new URL(request.url).searchParams.get('_method'),
                    new URL(request.url).pathname,
                    await sentData(request),
                );
                await network();

                return redirect(index.url(), 303);
            }),
            http.get(index.url(), () =>
                inertiaPage(
                    'admin/users/index',
                    {
                        users: {
                            ...userPage,
                            users: left,
                            total: userPage.total - 1,
                        },
                        filters: noFilters,
                        roles,
                    },
                    {
                        url: index.url(),
                        shared: sharedProps(adminAuth),
                        flash: {
                            toast: {
                                type: 'success',
                                message: 'Account deleted.',
                            },
                        },
                    },
                ),
            ),
        );
    },
    play: async (context) => {
        const { canvas, step } = context;

        await step("Delete Lotte's account, and say yes", async () => {
            await confirm(context, 'Delete account', 'Delete account');
        });

        await step('The list opens without Lotte', async () => {
            await expectToast(canvas, 'Account deleted.');
            await expect(
                await canvas.findByRole('heading', { name: 'Riders' }),
            ).toBeVisible();
            await expect(
                canvas.queryByRole('link', { name: lotte.name }),
            ).toBeNull();
            await expect(actionRequest).toHaveBeenCalledWith(
                'DELETE',
                destroy.url(lotte.id),
                {},
            );
        });
    },
};

/** A new rider: nothing connected, no routes. */
export const NewRider: Story = {
    args: {
        user: {
            ...lotte,
            ...seen(6),
            id: 6,
            name: 'Pieter de Vries',
            email: 'pieter@example.com',
            passkeys: 0,
            routes: 0,
            watches: [],
            apps: [],
            services: [],
            sessions: [
                {
                    browser: 'Chrome on Android',
                    last_active_at: seen(6).last_seen_at ?? '',
                },
            ],
        },
        activity: [],
    },
};
