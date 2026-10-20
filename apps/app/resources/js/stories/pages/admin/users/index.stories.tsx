import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, waitFor, within } from 'storybook/test';
import Users from '@/pages/admin/users/index';
import { index } from '@/routes/admin/users';
import type { AdminUserRow } from '@/types/admin';
import { noFilters, roles, userPage } from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { serverTime } from '../../../support/flows';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';
import { http, inertiaPage, network } from '../../../support/server';
import { followLinks } from '../../../support/story-links';

type UsersProps = ComponentProps<typeof Users>;

/**
 * The rider list at `/admin/users`, with the props AdminUsersController renders: a page of riders, the filters as
 * they came in the query, and the roles.
 *
 * The flow stories search and filter against a mocked server (Mock Service Worker) that reads the query the way
 * AdminUsersController@index does (`search`, `role`, `verified`, `two_factor`, `page`), filters the example riders
 * as AdminUsersQuery would, and answers with the list and the filters it used. "Add a rider" and Lotte Janssens
 * open those pages' stories.
 */
const meta = {
    title: 'Pages/Admin/Users/Index',
    component: Users,
    parameters: { layout: 'fullscreen' },
    args: { users: userPage, filters: noFilters, roles },
    decorators: [
        followLinks({
            '/admin/users/create': ['Pages/Admin/Users/Create', 'Default'],
            '/admin/users/3': ['Pages/Admin/Users/Show', 'Rider'],
        }),
    ],
    render: (args) => (
        <InertiaPage<UsersProps>
            name="admin/users/index"
            component={Users}
            url={index.url()}
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof Users>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The first of 52 pages, newest riders and staff mixed. */
export const Default: Story = {};

/** Riders whose email is not confirmed yet. */
export const Filtered: Story = {
    args: {
        users: {
            ...userPage,
            users: userPage.users.filter((user) => !user.verified),
            pages: 1,
            total: 2,
        },
        filters: { ...noFilters, verified: 'no' },
    },
};

/** A search that found nobody. */
export const NoMatch: Story = {
    args: {
        users: { ...userPage, users: [], pages: 1, total: 0 },
        filters: { ...noFilters, search: 'nobody@example.com' },
    },
};

/** The queries the mocked server got. */
const listRequest = fn();

/** Whether a yes/no filter keeps a rider, as AdminUsersController::flag() reads it. */
function flag(value: string, on: boolean): boolean {
    return value === '' || (value === 'yes') === on;
}

/** AdminUsersController@index over the example riders. */
const listRoute = http.get(index.url(), async ({ request }) => {
    const query = new URL(request.url).searchParams;
    const filters = {
        search: (query.get('search') ?? '').trim(),
        role: query.get('role') || null,
        verified: query.get('verified') ?? '',
        two_factor: query.get('two_factor') ?? '',
    };
    const search = filters.search.toLowerCase();
    const users = userPage.users.filter(
        (user: AdminUserRow) =>
            (search === '' ||
                user.name.toLowerCase().includes(search) ||
                user.email.toLowerCase().includes(search)) &&
            (filters.role === null || user.role === filters.role) &&
            flag(filters.verified, user.verified) &&
            flag(filters.two_factor, user.two_factor),
    );

    listRequest(Object.fromEntries(query));
    await network();

    return inertiaPage(
        'admin/users/index',
        {
            users: {
                ...userPage,
                users,
                page: 1,
                pages: 1,
                total: users.length,
            },
            filters,
            roles,
        },
        { url: request.url, shared: sharedProps(adminAuth) },
    );
});

/** The admin searches for "lotte", and the list narrows to Lotte Janssens. */
export const Searches: Story = {
    beforeEach({ msw }) {
        listRequest.mockClear();
        msw.use(listRoute);
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Search for lotte', async () => {
            await userEvent.type(
                canvas.getByRole('searchbox', { name: 'Name or email' }),
                'lotte',
            );
            await userEvent.click(
                canvas.getByRole('button', { name: 'Search' }),
            );
        });

        await step('Only Lotte Janssens is listed', async () => {
            await expect(
                await canvas.findByText('1 found, newest first.'),
            ).toBeVisible();

            const rows = within(canvas.getByRole('table')).getAllByRole('row');

            // The header row and Lotte.
            await expect(rows).toHaveLength(2);
            await expect(
                canvas.getByRole('link', { name: 'Lotte Janssens' }),
            ).toBeVisible();
            await expect(listRequest).toHaveBeenCalledWith(
                expect.objectContaining({ search: 'lotte' }),
            );
        });
    },
};

/**
 * The admin picks "Not confirmed": the form sends itself as the select changes, and the list shows the two
 * riders who have not opened their link. "Any email" brings everyone back.
 */
export const FiltersByEmail: Story = {
    beforeEach({ msw }) {
        listRequest.mockClear();
        msw.use(listRoute);
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Pick Not confirmed', async () => {
            await userEvent.selectOptions(
                canvas.getByRole('combobox', { name: 'Email' }),
                'no',
            );
        });

        await step('Two riders are listed', async () => {
            await expect(
                await canvas.findByText('2 found, newest first.'),
            ).toBeVisible();
            await expect(canvas.getByText('Pieter de Vries')).toBeVisible();
            await expect(canvas.getByText('Tom Claes')).toBeVisible();
            await expect(listRequest).toHaveBeenLastCalledWith(
                expect.objectContaining({ verified: 'no' }),
            );
        });

        await step('Pick Any email again', async () => {
            await userEvent.selectOptions(
                canvas.getByRole('combobox', { name: 'Email' }),
                '',
            );
            await waitFor(
                () =>
                    expect(
                        within(canvas.getByRole('table')).getAllByRole('row'),
                    ).toHaveLength(userPage.users.length + 1),
                serverTime,
            );
            await expect(listRequest).toHaveBeenLastCalledWith(
                expect.objectContaining({ verified: '' }),
            );
        });
    },
};
