import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';

/**
 * Diagonal hatching that holds the place of content still to come. It fills its box and takes its colour from
 * `stroke`.
 */
const meta = {
    title: 'UI/PlaceholderPattern',
    component: PlaceholderPattern,
    args: {
        className: 'size-full stroke-neutral-900/20 dark:stroke-neutral-100/20',
    },
    argTypes: {
        className: {
            control: 'text',
            description:
                'Size and hatching colour: `size-full` to fill its box, and a `stroke-…` class.',
        },
    },
    render: (args) => (
        <div className="relative h-40 max-w-sm overflow-hidden rounded-xl border">
            <PlaceholderPattern {...args} />
        </div>
    ),
} satisfies Meta<typeof PlaceholderPattern>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvasElement }) => {
        const pattern = canvasElement.querySelector('svg');

        // Decoration only: screen readers skip it.
        await expect(pattern).toHaveAttribute('aria-hidden', 'true');
        await expect(pattern?.getBoundingClientRect().height).toBeGreaterThan(
            0,
        );
    },
};

export const Grid: Story = {
    render: (args) => (
        <div className="grid max-w-xl grid-cols-3 gap-4">
            {['Distance', 'Climbing', 'Routes'].map((label) => (
                <div
                    key={label}
                    className="relative aspect-video overflow-hidden rounded-xl border"
                >
                    <PlaceholderPattern {...args} />
                    <span className="absolute inset-x-0 bottom-2 text-center text-xs text-muted-foreground">
                        {label}
                    </span>
                </div>
            ))}
        </div>
    ),
    play: async ({ canvas, canvasElement }) => {
        await expect(canvasElement.querySelectorAll('svg')).toHaveLength(3);
        await expect(canvas.getByText('Climbing')).toBeVisible();
    },
};
