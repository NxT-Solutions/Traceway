import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import TrafficRequests from '@/pages/admin/traffic/requests';
import { requests } from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';

type RequestsProps = ComponentProps<typeof TrafficRequests>;

/**
 * The Requests tab at `/admin/traffic/requests`: the last 72 hours, up to a year of days, and how requests were
 * answered. AdminTrafficInsightsController defers it; here it arrives with the page, as example numbers.
 */
const meta = {
    title: 'Pages/Admin/Traffic/Requests',
    component: TrafficRequests,
    parameters: { layout: 'fullscreen' },
    args: { insights: requests },
    render: (args) => (
        <InertiaPage<RequestsProps>
            name="admin/traffic/requests"
            component={TrafficRequests}
            url="/admin/traffic/requests"
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof TrafficRequests>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** When Cloudflare did not answer: a note in place of the numbers, never zeros that look like all clear. */
export const Unavailable: Story = {
    args: {
        insights: {
            ...requests,
            error: 'Cloudflare took too long to answer. Try again in a minute.',
        },
    },
};
