import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import TextLink from '@/components/text-link';

/**
 * A link inside a sentence: underlined, with the underline darkening on hover. It is an Inertia link, so it
 * visits the page without a full reload.
 */
const meta = {
    title: 'Components/TextLink',
    component: TextLink,
    args: { href: '/forgot-password', children: 'Forgot your password?' },
    argTypes: {
        href: { control: 'text', description: 'Where it goes in the app.' },
        children: { control: 'text', description: 'The words of the link.' },
        className: { control: 'text', description: 'Extra classes.' },
    },
} satisfies Meta<typeof TextLink>;

export default meta;

type Story = StoryObj<typeof meta>;

/** An underlined link, reached with Tab. */
export const Default: Story = {
    play: async ({ canvas, args, userEvent }) => {
        const link = canvas.getByRole('link', {
            name: String(args.children),
        });

        await expect(link).toHaveAttribute('href', args.href);
        await expect(link).toHaveClass('underline');
        await userEvent.tab();
        await expect(link).toHaveFocus();
    },
};

export const InASentence: Story = {
    render: (args) => (
        <p className="text-sm text-muted-foreground">
            New to Traceway? <TextLink {...args}>Sign up</TextLink>
        </p>
    ),
    args: { href: '/register' },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('link', { name: 'Sign up' }),
        ).toHaveAttribute('href', '/register');
    },
};
