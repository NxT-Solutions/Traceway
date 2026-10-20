import type { Meta, StoryObj } from '@storybook/react-vite';
import { Bike, Footprints } from 'lucide-react';
import { expect } from 'storybook/test';
import { MultisportIcon } from '@/components/multisport-icon';

/**
 * Swim, bike, and run in one mark, for the Multisport tab of the planner. It is drawn at the weight of the lucide
 * icons beside it, and hidden from screen readers: the word Multisport is always beside it.
 */
const meta = {
    title: 'Components/MultisportIcon',
    component: MultisportIcon,
    args: { size: 24 },
    argTypes: {
        size: {
            control: { type: 'range', min: 12, max: 64 },
            description: 'Width and height, in pixels.',
        },
        className: {
            control: 'text',
            description: 'Extra classes, such as a text colour.',
        },
    },
} satisfies Meta<typeof MultisportIcon>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Drawn at the size asked for, and hidden from screen readers. */
export const Default: Story = {
    play: async ({ canvasElement, args }) => {
        const mark = canvasElement.querySelector('svg');

        await expect(mark).toHaveAttribute('aria-hidden', 'true');
        await expect(mark).toHaveAttribute('width', String(args.size));
    },
};

/** Beside the lucide icons it sits with, at the same size and stroke. */
export const WithLucide: Story = {
    render: (args) => (
        <div className="flex items-center gap-4">
            <Footprints size={args.size} aria-hidden="true" />
            <Bike size={args.size} aria-hidden="true" />
            <MultisportIcon {...args} />
        </div>
    ),
    play: async ({ canvasElement }) => {
        const icons = [...canvasElement.querySelectorAll('svg')];

        await expect(icons).toHaveLength(3);

        for (const icon of icons) {
            await expect(icon).toHaveAttribute('stroke-width', '2');
            await expect(icon).toHaveAttribute(
                'width',
                icons[0]?.getAttribute('width') ?? '',
            );
        }
    },
};
