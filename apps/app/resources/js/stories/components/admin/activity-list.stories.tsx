import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect, within } from 'storybook/test';
import ActivityList from '@/components/admin/activity-list';
import { useAppearance } from '@/hooks/use-appearance';
import type { ActivityEntry } from '@/types/admin';

/** The admin area around the list, in Paper or Ink with the toolbar. */
function AdminScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div style={{ maxWidth: 720 }}>{children}</div>
        </div>
    );
}

const entries: ActivityEntry[] = [
    {
        id: 3,
        action: 'role.assigned',
        summary: 'Made an admin',
        at: '2026-10-19T09:14:00Z',
        actor: { id: 2, name: 'Alex Admin' },
        rider: { id: 1, name: 'Sam Rivera' },
    },
    {
        id: 2,
        action: 'connection.removed',
        summary: 'Disconnected Wahoo',
        at: '2026-10-18T17:02:00Z',
        actor: { id: 1, name: 'Sam Rivera' },
        rider: { id: 1, name: 'Sam Rivera' },
    },
    {
        id: 1,
        action: 'account.deleted',
        summary: 'Deleted the account',
        at: '2026-10-17T06:40:00Z',
        actor: null,
        rider: null,
    },
];

/**
 * Who did what to whose account, newest first. A deleted account shows as gone, and a change from the server as
 * the server. On one rider's page, `showRider` is off.
 */
const meta = {
    title: 'Components/Admin/ActivityList',
    component: ActivityList,
    args: { entries, empty: 'Nothing has happened yet.' },
    argTypes: {
        entries: {
            control: 'object',
            description:
                'What happened, newest first: a summary, who did it (null for the server), whose account (null when it is gone), and when.',
        },
        showRider: {
            control: 'boolean',
            description:
                "Names the rider with a link to their page. Off on one rider's own page.",
        },
        empty: {
            control: 'text',
            description: 'What the list says when nothing has happened.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <AdminScene>
                <Story />
            </AdminScene>
        ),
    ],
} satisfies Meta<typeof ActivityList>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Each entry links to the rider's page; a gone account and the server say so in words. */
export const Default: Story = {
    play: async ({ canvas, userEvent }) => {
        const items = within(canvas.getByRole('list')).getAllByRole('listitem');

        await expect(items).toHaveLength(3);
        await expect(items[0]).toHaveTextContent('Made an admin');
        await expect(items[0]).toHaveTextContent('Alex Admin');
        await expect(items[2]).toHaveTextContent('an account that is gone');
        await expect(items[2]).toHaveTextContent('The server');

        const riders = canvas.getAllByRole('link', { name: 'Sam Rivera' });
        await expect(riders).toHaveLength(2);
        await expect(riders[0]).toHaveAttribute('href', '/admin/users/1');

        // The rider links are the stops for the keyboard.
        await userEvent.tab();
        await expect(riders[0]).toHaveFocus();
        await userEvent.tab();
        await expect(riders[1]).toHaveFocus();
    },
};

export const OneRider: Story = {
    args: { entries: entries.slice(0, 2), showRider: false },
    play: async ({ canvas }) => {
        await expect(canvas.getAllByRole('listitem')).toHaveLength(2);
        await expect(canvas.queryByRole('link')).toBeNull();
    },
};

export const Empty: Story = {
    args: { entries: [] },
    play: async ({ args, canvas }) => {
        await expect(canvas.getByText(args.empty)).toBeVisible();
        await expect(canvas.queryByRole('list')).toBeNull();
    },
};
