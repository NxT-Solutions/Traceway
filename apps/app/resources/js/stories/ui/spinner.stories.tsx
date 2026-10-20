import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * Something is on its way. It says "Loading" to screen readers; inside a button that already says what is
 * happening, hide it with `aria-hidden`.
 */
const meta = {
    title: 'UI/Spinner',
    component: Spinner,
    argTypes: {
        className: {
            control: 'text',
            description: 'Size and colour, such as `size-8`. 16px by default.',
        },
        'aria-label': {
            control: 'text',
            description: 'What screen readers hear.',
            table: { defaultValue: { summary: 'Loading' } },
        },
        'aria-hidden': {
            control: 'boolean',
            description:
                'Hide it when the words beside it already say what is happening.',
        },
    },
} satisfies Meta<typeof Spinner>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvas }) => {
        const spinner = canvas.getByRole('status', { name: 'Loading' });

        await expect(spinner).toHaveClass('animate-spin');
        await expect(spinner.getBoundingClientRect().width).toBe(16);
    },
};

export const Large: Story = {
    args: { className: 'size-8' },
    play: async ({ canvas }) => {
        await expect(
            canvas
                .getByRole('status', { name: 'Loading' })
                .getBoundingClientRect().width,
        ).toBe(32);
    },
};

export const InAButton: Story = {
    render: (args) => (
        <Button disabled>
            <Spinner {...args} aria-hidden="true" />
            Drawing the route
        </Button>
    ),
    play: async ({ canvas }) => {
        // The button says it; the spinner stays quiet.
        await expect(
            canvas.getByRole('button', { name: 'Drawing the route' }),
        ).toBeDisabled();
        await expect(canvas.queryByRole('status')).not.toBeInTheDocument();
    },
};
