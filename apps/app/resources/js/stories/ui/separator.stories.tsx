import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { Separator } from '@/components/ui/separator';

/**
 * A hairline between groups. Decorative by default, so screen readers skip it.
 */
const meta = {
    title: 'UI/Separator',
    component: Separator,
    args: { orientation: 'horizontal' },
    argTypes: {
        orientation: {
            control: 'inline-radio',
            options: ['horizontal', 'vertical'],
            description:
                'A line across (between rows) or up (between items in a row).',
            table: { defaultValue: { summary: 'horizontal' } },
        },
        decorative: {
            control: 'boolean',
            description:
                'Skipped by screen readers. Turn off only when the line separates content a reader should hear as two parts.',
            table: { defaultValue: { summary: 'true' } },
        },
    },
    render: (args) => (
        <div className="max-w-sm text-sm">
            <p className="font-medium">Morning loop</p>
            <p className="text-muted-foreground">Road, from Westerlo</p>
            <Separator {...args} className="my-4" />
            <p>41.6 km · 180 m up</p>
        </div>
    ),
} satisfies Meta<typeof Separator>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Horizontal: Story = {
    play: async ({ canvasElement }) => {
        const line = canvasElement.querySelector(
            '[data-slot="separator-root"]',
        );

        await expect(line).toHaveAttribute('data-orientation', 'horizontal');
        await expect(line).toHaveAttribute('role', 'none');
        await expect(line?.getBoundingClientRect().height).toBe(1);
    },
};

export const Vertical: Story = {
    args: { orientation: 'vertical' },
    render: (args) => (
        <div className="flex h-5 items-center gap-4 text-sm">
            <span>41.6 km</span>
            <Separator {...args} />
            <span>180 m up</span>
            <Separator {...args} />
            <span>Paved</span>
        </div>
    ),
    play: async ({ canvasElement }) => {
        const lines = canvasElement.querySelectorAll(
            '[data-slot="separator-root"]',
        );

        await expect(lines).toHaveLength(2);
        for (const line of lines) {
            await expect(line).toHaveAttribute('data-orientation', 'vertical');
            await expect(line.getBoundingClientRect().width).toBe(1);
        }
    },
};

/** Not decorative: screen readers hear a separator between the two parts. */
export const Semantic: Story = {
    args: { decorative: false },
    play: async ({ canvas }) => {
        await expect(canvas.getByRole('separator')).toBeInTheDocument();
    },
};
