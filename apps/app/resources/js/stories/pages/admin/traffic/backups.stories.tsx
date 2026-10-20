import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import TrafficBackups from '@/pages/admin/traffic/backups';
import { backups } from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';

type BackupsProps = ComponentProps<typeof TrafficBackups>;

/**
 * The Backups tab at `/admin/traffic/backups`: whether Litestream still writes the database to R2.
 * AdminTrafficInsightsController defers it; here it arrives with the page, as example numbers.
 */
const meta = {
    title: 'Pages/Admin/Traffic/Backups',
    component: TrafficBackups,
    parameters: { layout: 'fullscreen' },
    args: { insights: backups },
    render: (args) => (
        <InertiaPage<BackupsProps>
            name="admin/traffic/backups"
            component={TrafficBackups}
            url="/admin/traffic/backups"
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof TrafficBackups>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The last file went in today. */
export const Default: Story = {};

/** Nothing written for a while: the alert to check Litestream. */
export const Stopped: Story = {
    args: {
        insights: {
            ...backups,
            write_days: backups.write_days.map((day, index, days) =>
                index >= days.length - 4 ? { ...day, count: 0 } : day,
            ),
            last_write: backups.write_days.at(-5)?.date ?? backups.last_write,
        },
    },
};

/** Before the bucket is set on the server. */
export const NoBucket: Story = {
    args: { insights: { ...backups, bucket: null } },
};
