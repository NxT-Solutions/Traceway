import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect } from 'storybook/test';
import StatTile from '@/components/admin/stat-tile';
import { useAppearance } from '@/hooks/use-appearance';

/** The admin area around the tiles, in Paper or Ink with the toolbar. */
function AdminScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div className="tiles">{children}</div>
        </div>
    );
}

/**
 * One number on the admin overview: what it counts, the number in large type, and a line of detail under it.
 * Numbers get thousands separators; text shows as it is.
 */
const meta = {
    title: 'Components/Admin/StatTile',
    component: StatTile,
    args: { label: 'Riders', value: 1284, detail: '+36 this week' },
    argTypes: {
        label: { control: 'text', description: 'What the number counts.' },
        value: {
            control: 'number',
            description:
                'The number, shown with thousands separators. Text, such as "41.6 km", shows as it is.',
        },
        detail: {
            control: 'text',
            description:
                'One short line under the number. Left out when empty.',
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
} satisfies Meta<typeof StatTile>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The number gets its thousands separator; the label and the detail read around it. */
export const Default: Story = {
    play: async ({ canvas }) => {
        await expect(canvas.getByText('Riders')).toBeVisible();
        await expect(canvas.getByText('1,284')).toBeVisible();
        await expect(canvas.getByText('+36 this week')).toBeVisible();
    },
};

export const Overview: Story = {
    render: () => (
        <>
            <StatTile label="Riders" value={1284} detail="+36 this week" />
            <StatTile
                label="Routes planned"
                value={18_412}
                detail="412 today"
            />
            <StatTile
                label="Sent to devices"
                value={6_903}
                detail="Garmin 71%"
            />
            <StatTile label="Median loop" value="41.6 km" detail="180 m up" />
        </>
    ),
    play: async ({ canvas, canvasElement }) => {
        await expect(canvasElement.querySelectorAll('.tile')).toHaveLength(4);
        await expect(canvas.getByText('18,412')).toBeVisible();
        await expect(canvas.getByText('6,903')).toBeVisible();
        // Text shows as it is, not as a number.
        await expect(canvas.getByText('41.6 km')).toBeVisible();
    },
};

export const NoDetail: Story = {
    args: { detail: undefined },
    play: async ({ canvas, canvasElement }) => {
        await expect(canvas.getByText('1,284')).toBeVisible();
        await expect(canvasElement.querySelector('.tile .d')).toBeNull();
    },
};
