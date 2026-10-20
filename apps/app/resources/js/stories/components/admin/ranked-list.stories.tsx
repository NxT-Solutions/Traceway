import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect, within } from 'storybook/test';
import RankedList from '@/components/admin/ranked-list';
import { useAppearance } from '@/hooks/use-appearance';

/** The admin area around the list, in Paper or Ink with the toolbar. */
function AdminScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <section className="panel" style={{ maxWidth: 480 }}>
                <div className="panel-head">
                    <h3>Routes by sport</h3>
                </div>
                {children}
            </section>
        </div>
    );
}

/**
 * Parts of a whole, largest first, as labelled bars. The label and the number carry the meaning, so the bars need
 * no colour to read. An empty list says so in words.
 */
const meta = {
    title: 'Components/Admin/RankedList',
    component: RankedList,
    args: {
        empty: 'No routes yet.',
        items: [
            { label: 'Road', count: 6_412 },
            { label: 'Gravel', count: 3_870 },
            { label: 'Run', count: 3_105 },
            { label: 'Trail run', count: 1_942 },
            { label: 'Hike', count: 1_288 },
            { label: 'Mountain bike', count: 904 },
            { label: 'Walk', count: 611 },
        ],
    },
    argTypes: {
        items: {
            control: 'object',
            description:
                'Each part as a label and a count, largest first. The longest bar is the largest count.',
        },
        empty: {
            control: 'text',
            description: 'What the list says when there is nothing in it.',
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
} satisfies Meta<typeof RankedList>;

export default meta;

type Story = StoryObj<typeof meta>;

/** An ordered list: every item reads as its label and its number, and the bar is hidden from screen readers. */
export const Sports: Story = {
    play: async ({ args, canvas }) => {
        const items = within(canvas.getByRole('list')).getAllByRole('listitem');

        await expect(items).toHaveLength(args.items.length);
        await expect(items[0]).toHaveTextContent('Road6,412');
        await expect(items.at(-1)).toHaveTextContent('Walk611');

        for (const item of items) {
            await expect(item.querySelector('.bar')).toHaveAttribute(
                'aria-hidden',
                'true',
            );
        }
    },
};

export const Devices: Story = {
    args: {
        items: [
            { label: 'Edge 540', count: 1_204 },
            { label: 'Forerunner 965', count: 988 },
            { label: 'Elemnt Bolt', count: 412 },
            { label: 'Karoo', count: 96 },
        ],
    },
    play: async ({ canvas, canvasElement }) => {
        await expect(canvas.getByText('Edge 540')).toBeVisible();
        await expect(canvas.getByText('1,204')).toBeVisible();
        // The largest count fills its bar.
        await expect(
            canvasElement.querySelector<HTMLElement>('.bar i')?.style.width,
        ).toBe('100%');
    },
};

export const Empty: Story = {
    args: { items: [] },
    play: async ({ args, canvas }) => {
        await expect(canvas.getByText(args.empty)).toBeVisible();
        await expect(canvas.queryByRole('list')).toBeNull();
    },
};
