import type { Meta, StoryObj } from '@storybook/react-vite';
import { Check } from 'lucide-react';
import { expect } from 'storybook/test';
import { Badge } from '@/components/ui/badge';

/**
 * A small label beside a name: a sport, a state, a role. One or two words, never a sentence.
 */
const meta = {
    title: 'UI/Badge',
    component: Badge,
    args: { children: 'Gravel' },
    argTypes: {
        variant: {
            control: 'inline-radio',
            options: ['default', 'secondary', 'destructive', 'outline'],
            description:
                '`default` fills with the marker, `secondary` and `outline` are quiet, `destructive` is for a state that needs the rider.',
            table: { defaultValue: { summary: 'default' } },
        },
        children: {
            control: 'text',
            description: 'One or two words.',
        },
        asChild: {
            control: 'boolean',
            description: 'Render the child element (a link) with badge styles.',
        },
    },
} satisfies Meta<typeof Badge>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvas }) => {
        await expect(canvas.getByText('Gravel')).toBeVisible();
    },
};

export const Variants: Story = {
    render: (args) => (
        <div className="flex flex-wrap items-center gap-2">
            <Badge {...args}>Road</Badge>
            <Badge {...args} variant="secondary">
                Trail run
            </Badge>
            <Badge {...args} variant="outline">
                Admin
            </Badge>
            <Badge {...args} variant="destructive">
                Needs reconnect
            </Badge>
        </div>
    ),
    play: async ({ canvas }) => {
        for (const label of ['Road', 'Trail run', 'Admin', 'Needs reconnect']) {
            await expect(canvas.getByText(label)).toBeVisible();
        }
    },
};

export const WithIcon: Story = {
    args: {
        variant: 'secondary',
        children: (
            <>
                <Check />
                Sent to Wahoo
            </>
        ),
    },
    play: async ({ canvas }) => {
        const badge = canvas.getByText('Sent to Wahoo');

        await expect(badge.querySelector('svg')).toBeInTheDocument();
    },
};
