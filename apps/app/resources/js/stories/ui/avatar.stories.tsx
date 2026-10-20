import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

/**
 * The rider's picture, or their initials when there is none. The account menu and the admin lists use it.
 */
const meta = {
    title: 'UI/Avatar',
    component: Avatar,
    argTypes: {
        className: {
            control: 'text',
            description:
                'Size and type, such as `size-12 text-lg`. 32px by default.',
        },
    },
    render: (args) => (
        <Avatar {...args}>
            <AvatarFallback>SR</AvatarFallback>
        </Avatar>
    ),
} satisfies Meta<typeof Avatar>;

export default meta;

type Story = StoryObj<typeof meta>;

/** No picture: the initials of Sam Rivera. */
export const Initials: Story = {
    play: async ({ canvas }) => {
        await expect(canvas.getByText('SR')).toBeVisible();
    },
};

/** A picture that does not load falls back to the initials. */
export const MissingPicture: Story = {
    render: (args) => (
        <Avatar {...args}>
            <AvatarImage src="/missing-avatar.png" alt="Sam Rivera" />
            <AvatarFallback>SR</AvatarFallback>
        </Avatar>
    ),
    play: async ({ canvas }) => {
        await expect(await canvas.findByText('SR')).toBeVisible();
        await expect(canvas.queryByRole('img')).not.toBeInTheDocument();
    },
};

export const Sizes: Story = {
    render: (args) => (
        <div className="flex items-center gap-3">
            <Avatar {...args} className="size-6 text-xs">
                <AvatarFallback>SR</AvatarFallback>
            </Avatar>
            <Avatar {...args}>
                <AvatarFallback>SR</AvatarFallback>
            </Avatar>
            <Avatar {...args} className="size-12 text-lg">
                <AvatarFallback>SR</AvatarFallback>
            </Avatar>
        </div>
    ),
    play: async ({ canvasElement }) => {
        const widths = [
            ...canvasElement.querySelectorAll('[data-slot="avatar"]'),
        ].map((avatar) => avatar.getBoundingClientRect().width);

        await expect(widths).toEqual([24, 32, 48]);
    },
};
