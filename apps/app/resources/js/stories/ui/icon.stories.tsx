import type { Meta, StoryObj } from '@storybook/react-vite';
import { Bike, Footprints, Map as MapIcon, Mountain } from 'lucide-react';
import { expect } from 'storybook/test';
import { Icon } from '@/components/ui/icon';

/**
 * Draws a lucide icon passed as a value, such as the icon of a navigation item. Nothing at all when there is no
 * icon, so a menu can mix items with and without one.
 */
const meta = {
    title: 'UI/Icon',
    component: Icon,
    args: { iconNode: Bike, className: 'size-6' },
    argTypes: {
        iconNode: {
            control: false,
            description:
                'The lucide icon component, such as `Bike`. `null` or unset draws nothing.',
        },
        className: {
            control: 'text',
            description:
                'Size and colour, such as `size-6 text-muted-foreground`.',
        },
    },
} satisfies Meta<typeof Icon>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvasElement }) => {
        const icon = canvasElement.querySelector('svg');

        await expect(icon).toHaveClass('lucide-bike', 'size-6');
        await expect(icon?.getBoundingClientRect().width).toBe(24);
    },
};

export const Sports: Story = {
    render: (args) => (
        <div className="flex items-center gap-4">
            <Icon {...args} iconNode={Bike} />
            <Icon {...args} iconNode={Footprints} />
            <Icon {...args} iconNode={Mountain} />
            <Icon {...args} iconNode={MapIcon} />
        </div>
    ),
    play: async ({ canvasElement }) => {
        await expect(canvasElement.querySelectorAll('svg')).toHaveLength(4);
    },
};

/** No icon: nothing renders. */
export const Empty: Story = {
    render: (args) => <Icon {...args} iconNode={null} />,
    play: async ({ canvasElement }) => {
        await expect(canvasElement.querySelector('svg')).toBeNull();
    },
};
