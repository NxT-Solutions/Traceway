import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import TrafficSpeed from '@/pages/admin/traffic/speed';
import { speed } from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';

type SpeedProps = ComponentProps<typeof TrafficSpeed>;

/**
 * The Speed tab at `/admin/traffic/speed`: a week of Core Web Vitals and load times, by page, from visitors who said
 * yes to counting. AdminTrafficInsightsController defers it; here it arrives with the page, as example numbers.
 */
const meta = {
    title: 'Pages/Admin/Traffic/Speed',
    component: TrafficSpeed,
    parameters: { layout: 'fullscreen' },
    args: { insights: speed },
    render: (args) => (
        <InertiaPage<SpeedProps>
            name="admin/traffic/speed"
            component={TrafficSpeed}
            url="/admin/traffic/speed"
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof TrafficSpeed>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Good, needs work, and poor, with a page that was not measured. */
export const Default: Story = {};

/** A week nobody said yes to counting. */
export const NothingMeasured: Story = {
    args: { insights: { ...speed, vitals: [], timings: [] } },
};
