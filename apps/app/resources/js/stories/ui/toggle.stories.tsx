import type { Meta, StoryObj } from '@storybook/react-vite';
import { Mountain } from 'lucide-react';
import { expect, fn } from 'storybook/test';
import { Toggle } from '@/components/ui/toggle';

/**
 * A button that stays pressed, for one setting that is on or off, such as steepness on the map.
 */
const meta = {
    title: 'UI/Toggle',
    component: Toggle,
    args: {
        'aria-label': 'Steepness on map',
        children: (
            <>
                <Mountain />
                Steepness
            </>
        ),
        onPressedChange: fn(),
    },
    argTypes: {
        variant: {
            control: 'inline-radio',
            options: ['default', 'outline'],
            description: '`outline` draws a frame round it.',
            table: { defaultValue: { summary: 'default' } },
        },
        size: {
            control: 'inline-radio',
            options: ['default', 'sm', 'lg'],
            description: 'Height: 32, 36 or 40px.',
            table: { defaultValue: { summary: 'default' } },
        },
        pressed: {
            control: 'boolean',
            description:
                'Controlled state. Leave unset to let it keep its own.',
        },
        defaultPressed: {
            control: 'boolean',
            description: 'Whether it starts on.',
        },
        disabled: {
            control: 'boolean',
            description: 'Turned off.',
        },
        'aria-label': {
            control: 'text',
            description: 'Its name; it must contain the visible word.',
        },
        children: { control: false, description: 'An icon and a word.' },
        onPressedChange: {
            action: 'pressed changed',
            description:
                'Called with `true` when it turns on, `false` when off.',
        },
    },
} satisfies Meta<typeof Toggle>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Off: Story = {
    play: async ({ args, canvas, userEvent }) => {
        const toggle = canvas.getByRole('button', { name: 'Steepness on map' });

        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await userEvent.click(toggle);
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(args.onPressedChange).toHaveBeenLastCalledWith(true);
        // Space turns it off again.
        await userEvent.keyboard(' ');
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await expect(args.onPressedChange).toHaveBeenLastCalledWith(false);
    },
};

export const On: Story = {
    args: { defaultPressed: true },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Steepness on map' }),
        ).toHaveAttribute('aria-pressed', 'true');
    },
};

export const Outline: Story = {
    args: { variant: 'outline' },
    play: async ({ args, canvas, userEvent }) => {
        await userEvent.tab();
        const toggle = canvas.getByRole('button', { name: 'Steepness on map' });

        await expect(toggle).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(args.onPressedChange).toHaveBeenCalledWith(true);
    },
};

export const Disabled: Story = {
    args: { disabled: true },
    play: async ({ args, canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Steepness on map' }),
        ).toBeDisabled();
        await expect(args.onPressedChange).not.toHaveBeenCalled();
    },
};
