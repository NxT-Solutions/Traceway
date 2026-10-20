import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect } from 'storybook/test';
import Sparkline from '@/components/admin/sparkline';
import { useAppearance } from '@/hooks/use-appearance';

/** The admin area around the line, in Paper or Ink with the toolbar. */
function AdminScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div className="flex items-center gap-4">
                <span className="num text-2xl font-semibold">412</span>
                {children}
            </div>
        </div>
    );
}

/**
 * A small trend beside a total. The number next to it carries the meaning, so the line is decoration and hidden
 * from screen readers.
 */
const meta = {
    title: 'Components/Admin/Sparkline',
    component: Sparkline,
    args: { values: [212, 260, 241, 298, 330, 318, 365, 341, 390, 412] },
    argTypes: {
        values: {
            control: 'object',
            description:
                'The counts, oldest first, one point each. The line is scaled to the largest, and zeros sit on the floor.',
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
} satisfies Meta<typeof Sparkline>;

export default meta;

type Story = StoryObj<typeof meta>;

/** One point per value, and out of the accessibility tree: the total beside it carries the meaning. */
export const Rising: Story = {
    play: async ({ args, canvas, canvasElement }) => {
        await expect(canvas.getByText('412')).toBeVisible();

        const line = canvasElement.querySelector('svg.spark');
        await expect(line).toHaveAttribute('aria-hidden', 'true');

        const points = line
            ?.querySelector('polyline')
            ?.getAttribute('points')
            ?.split(' ');
        await expect(points).toHaveLength(args.values.length);
    },
};

export const Quiet: Story = {
    args: { values: [0, 0, 1, 0, 2, 0, 0] },
    play: Rising.play,
};
