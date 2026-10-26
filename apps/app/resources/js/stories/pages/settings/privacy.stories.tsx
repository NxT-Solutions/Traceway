import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn } from 'storybook/test';
import Privacy from '@/pages/settings/privacy';
import { exportMethod } from '@/routes/privacy';
import { rider } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { HttpResponse, http } from '../../support/server';
import { followLinks } from '../../support/story-links';

type Props = ComponentProps<typeof Privacy>;

/**
 * Settings, Your data (`/settings/privacy`): what Traceway keeps about the rider, a download of all of it, and the
 * AI assistants that can plan for them. Saved routes are listed here; they are deleted from the library. The roads the
 * rider avoids are listed too; they are removed on the planner map.
 *
 * `ExportsData` runs against a mocked server (Mock Service Worker) that answers the download as PrivacyController
 * does: `traceway-data.json` as an attachment. "Connections" and "Manage AI assistants" open those pages' stories.
 */
const meta = {
    title: 'Pages/Settings/Privacy',
    parameters: { layout: 'fullscreen' },
    decorators: [
        followLinks({
            '/settings/connections': [
                'Pages/Settings/Connections',
                'Connected',
            ],
            '/settings/assistants': ['Pages/Settings/Assistants', 'Connected'],
        }),
    ],
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const account: Props['account'] = {
    name: rider.name,
    email: rider.email,
    email_verified_at: rider.email_verified_at,
    two_factor_enabled: false,
    created_at: rider.created_at,
};

function page(props: Props) {
    return () => (
        <InertiaPage
            name="settings/privacy"
            component={Privacy}
            props={props}
            shared={sharedProps()}
            url="/settings/privacy"
        />
    );
}

const withRoutes: Props = {
    account,
    routes: [
        {
            name: 'Morning loop',
            sport: 'road',
            distance_metres: 42_300,
            created_at: '2026-10-18T06:10:00Z',
        },
        {
            name: 'Riverside 10k',
            sport: 'run',
            distance_metres: 10_100,
            created_at: '2026-10-05T17:45:00Z',
        },
        {
            name: 'Forest tracks',
            sport: 'gravel',
            distance_metres: 63_800,
            created_at: '2026-09-21T08:30:00Z',
        },
    ],
    avoided: [
        { name: 'Bosweg', created_at: '2026-10-19T09:12:00Z' },
        { name: null, created_at: '2026-10-22T16:40:00Z' },
    ],
    apps: [
        {
            id: '0199f2a4-6c1b-7d3e-9a8f-2b4c6d8e0f12',
            name: 'Claude',
            connected_at: '2026-10-10T19:00:00Z',
        },
    ],
};

/** Three saved routes, two roads avoided (one has no name), and Claude connected. */
export const WithRoutes: Story = {
    render: page(withRoutes),
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('heading', { name: 'Roads you avoid' }),
        ).toBeVisible();
        await expect(canvas.getByText('Bosweg')).toBeVisible();
        await expect(canvas.getByText('Unnamed road')).toBeVisible();
    },
};

/** A new account: nothing saved, no assistants, email not confirmed yet. */
export const Empty: Story = {
    render: page({
        account: { ...account, email_verified_at: null },
        routes: [],
        avoided: [],
        apps: [],
    }),
    play: async ({ canvas }) => {
        await expect(
            canvas.getByText(
                'None yet. Use Avoid a road in the planner to add one.',
            ),
        ).toBeVisible();
    },
};

/** Where the download link went, instead of leaving the story. */
const download = fn();

/**
 * Sam downloads everything Traceway keeps. The link is a plain download, not an Inertia visit, so the story stops
 * the browser from leaving and fetches the same address from the mocked server: a JSON file named
 * `traceway-data.json`, with the account and the saved routes.
 */
export const ExportsData: Story = {
    render: page(withRoutes),
    beforeEach({ msw }) {
        download.mockClear();
        msw.use(
            http.get(exportMethod.url(), () =>
                HttpResponse.json(
                    {
                        account: { name: rider.name, email: rider.email },
                        routes: withRoutes.routes.map((route) => ({
                            name: route.name,
                            sport: route.sport,
                        })),
                    },
                    {
                        headers: {
                            'Content-Disposition':
                                'attachment; filename=traceway-data.json',
                        },
                    },
                ),
            ),
        );

        const stayOnStory = (event: MouseEvent) => {
            const link = (event.target as Element).closest('a');

            if (link?.pathname === exportMethod.url()) {
                event.preventDefault();
                download(link.pathname);
            }
        };

        document.addEventListener('click', stayOnStory, true);

        return () => document.removeEventListener('click', stayOnStory, true);
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Download your data', async () => {
            await userEvent.click(
                canvas.getByRole('link', { name: 'Download your data' }),
            );
            await expect(download).toHaveBeenCalledWith(exportMethod.url());
        });

        await step('The server sends traceway-data.json', async () => {
            const response = await fetch(exportMethod.url());
            const file = await response.json();

            await expect(response.headers.get('Content-Disposition')).toBe(
                'attachment; filename=traceway-data.json',
            );
            await expect(file.routes).toContainEqual({
                name: 'Morning loop',
                sport: 'road',
            });
        });
    },
};
