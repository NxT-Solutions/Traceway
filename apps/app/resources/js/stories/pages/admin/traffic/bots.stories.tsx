import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import TrafficBots from '@/pages/admin/traffic/bots';
import { bots } from '../../../support/admin-pages';
import { adminAuth } from '../../../support/fixtures';
import { InertiaPage, sharedProps } from '../../../support/inertia-page';

type BotsProps = ComponentProps<typeof TrafficBots>;

/**
 * The AI and bots tab at `/admin/traffic/bots`: verified crawlers, who read the files for assistants, impostors,
 * and probes for secret files. AdminTrafficInsightsController defers it; here it arrives with the page, as example
 * numbers.
 */
const meta = {
    title: 'Pages/Admin/Traffic/Bots',
    component: TrafficBots,
    parameters: { layout: 'fullscreen' },
    args: { insights: bots },
    render: (args) => (
        <InertiaPage<BotsProps>
            name="admin/traffic/bots"
            component={TrafficBots}
            url="/admin/traffic/bots"
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof TrafficBots>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Every probe for a secret file was turned away. */
export const Default: Story = {};

/** A probe for a secret file was answered: the warning at the top. */
export const SecretFileAnswered: Story = {
    args: {
        insights: {
            ...bots,
            probes: [{ status: 200, count: 3 }, ...bots.probes],
            exposed: [{ label: '/.env.example', count: 3 }],
        },
    },
};
