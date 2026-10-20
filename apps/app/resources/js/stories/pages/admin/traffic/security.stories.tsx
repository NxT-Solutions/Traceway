import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import TrafficSecurity from '@/pages/admin/traffic/security';
import { security } from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';

type SecurityProps = ComponentProps<typeof TrafficSecurity>;

/**
 * The Security tab at `/admin/traffic/security`: what Cloudflare stopped before the server, and the sign-up check.
 * AdminTrafficInsightsController defers it; here it arrives with the page, as example numbers.
 */
const meta = {
    title: 'Pages/Admin/Traffic/Security',
    component: TrafficSecurity,
    parameters: { layout: 'fullscreen' },
    args: { insights: security },
    render: (args) => (
        <InertiaPage<SecurityProps>
            name="admin/traffic/security"
            component={TrafficSecurity}
            url="/admin/traffic/security"
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof TrafficSecurity>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A rate limit that also stopped people on the sign-up and log-in pages, flagged at the top. */
export const Default: Story = {};

/** No rule touched a page people use. */
export const NothingForPeople: Story = {
    args: { insights: { ...security, people_pages: [], people_blocked: 0 } },
};
