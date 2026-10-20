import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * The shape of content that is on its way, pulsing gently. Size it like what replaces it, so nothing jumps.
 */
const meta = {
    title: 'UI/Skeleton',
    component: Skeleton,
    args: { className: 'h-4 w-48' },
    argTypes: {
        className: {
            control: 'text',
            description:
                'Size it like what replaces it, such as `h-4 w-48` for a line or `size-10 rounded-xl` for a mark.',
        },
    },
} satisfies Meta<typeof Skeleton>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Line: Story = {
    play: async ({ canvasElement }) => {
        const line = canvasElement.querySelector('[data-slot="skeleton"]');

        await expect(line).toHaveClass('animate-pulse');
        await expect(line?.getBoundingClientRect()).toMatchObject({
            width: 192,
            height: 16,
        });
    },
};

/** A saved route while the list loads: a mark, a name, and a line of numbers. */
export const RouteRow: Story = {
    render: (args) => (
        <div className="flex max-w-sm items-center gap-4">
            <Skeleton {...args} className="size-10 rounded-xl" />
            <div className="grid flex-1 gap-2">
                <Skeleton {...args} className="h-4 w-2/3" />
                <Skeleton {...args} className="h-3 w-1/2" />
            </div>
        </div>
    ),
    play: async ({ canvasElement }) => {
        await expect(
            canvasElement.querySelectorAll('[data-slot="skeleton"]'),
        ).toHaveLength(3);
    },
};
