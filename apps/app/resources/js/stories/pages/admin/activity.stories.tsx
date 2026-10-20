import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import Activity from '@/pages/admin/activity';
import { activityPage } from '../../support/admin-pages';
import { adminAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';

type ActivityProps = ComponentProps<typeof Activity>;

/**
 * The admin log at `/admin/activity`, with the props AdminActivityController renders: who changed whose account,
 * newest first, 30 to a page. A change the server made shows as the server; a deleted account as gone.
 */
const meta = {
    title: 'Pages/Admin/Activity',
    component: Activity,
    parameters: { layout: 'fullscreen' },
    args: { activity: activityPage },
    render: (args) => (
        <InertiaPage<ActivityProps>
            name="admin/activity"
            component={Activity}
            url="/admin/activity"
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof Activity>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Before any admin changed an account. */
export const Empty: Story = {
    args: { activity: { entries: [], page: 1, pages: 1, total: 0 } },
};
