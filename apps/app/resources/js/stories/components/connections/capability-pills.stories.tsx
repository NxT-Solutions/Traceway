import '../../../../css/settings-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect, within } from 'storybook/test';
import CapabilityPills from '@/components/connections/capability-pills';
import { useAppearance } from '@/hooks/use-appearance';

/** The Connections page around the pills, in Paper or Ink with the toolbar. */
function SettingsScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <p className="cx-part-title" id="does">
                What Wahoo does
            </p>
            {children}
        </div>
    );
}

/**
 * What a connection does. What works today has a tick; what comes later says Later in words, not only in a fainter
 * colour. `nowOnly` leaves out what is not built yet.
 */
const meta = {
    title: 'Components/Connections/CapabilityPills',
    component: CapabilityPills,
    args: {
        labelledBy: 'does',
        capabilities: [
            { key: 'sign_in', label: 'Log in', later: false },
            { key: 'send_routes', label: 'Send routes', later: false },
            { key: 'import_routes', label: 'Import routes', later: true },
            { key: 'sync_routes', label: 'Sync routes', later: true },
        ],
    },
    argTypes: {
        capabilities: {
            control: 'object',
            description:
                'What the connection does, each with `later: true` when it is not built yet.',
        },
        labelledBy: {
            control: 'text',
            description: 'The id of the words that name the list.',
        },
        nowOnly: {
            control: 'boolean',
            description:
                'Leaves out what is not built yet, as on a connected account.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <SettingsScene>
                <Story />
            </SettingsScene>
        ),
    ],
} satisfies Meta<typeof CapabilityPills>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A list named by the words above it. What comes later says Later in words. */
export const NowAndLater: Story = {
    play: async ({ canvas }) => {
        const list = canvas.getByRole('list', { name: 'What Wahoo does' });
        const pills = within(list).getAllByRole('listitem');

        await expect(pills.map((pill) => pill.textContent)).toEqual([
            'Log in',
            'Send routes',
            'Import routes Later',
            'Sync routes Later',
        ]);
    },
};

export const NowOnly: Story = {
    args: { nowOnly: true },
    play: async ({ canvas }) => {
        const list = canvas.getByRole('list', { name: 'What Wahoo does' });

        await expect(within(list).getAllByRole('listitem')).toHaveLength(2);
        await expect(within(list).queryByText('Later')).toBeNull();
    },
};
