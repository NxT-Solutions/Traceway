import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

/**
 * A row of toggles where one choice is on, such as how hilly a route may be. Name the group, and keep each label
 * to a word.
 */
const meta = {
    title: 'UI/ToggleGroup',
    component: ToggleGroup,
    args: {
        type: 'single',
        defaultValue: 'some',
        variant: 'outline',
        'aria-label': 'Hills',
        onValueChange: fn(),
    },
    argTypes: {
        type: {
            control: 'inline-radio',
            options: ['single', 'multiple'],
            description: '`single`: one choice is on. `multiple`: any number.',
        },
        defaultValue: {
            control: 'inline-radio',
            options: ['flat', 'some', 'hilly'],
            description: 'The choice that starts on.',
        },
        variant: {
            control: 'inline-radio',
            options: ['default', 'outline'],
            description: '`outline` draws a frame round the row.',
            table: { defaultValue: { summary: 'default' } },
        },
        size: {
            control: 'inline-radio',
            options: ['default', 'sm', 'lg'],
            description: 'Height of each toggle: 32, 36 or 40px.',
            table: { defaultValue: { summary: 'default' } },
        },
        disabled: {
            control: 'boolean',
            description: 'Every toggle off.',
        },
        orientation: {
            control: 'inline-radio',
            options: ['horizontal', 'vertical'],
            description: 'Which arrow keys move between toggles.',
        },
        loop: {
            control: 'boolean',
            description:
                'The arrow keys wrap from the last toggle to the first.',
            table: { defaultValue: { summary: 'true' } },
        },
        'aria-label': {
            control: 'text',
            description: 'The name of the group, read before its choices.',
        },
        onValueChange: {
            action: 'value changed',
            description: 'Called with the choice that is now on.',
        },
    },
    render: (args) => (
        <ToggleGroup {...args}>
            <ToggleGroupItem value="flat" className="px-3">
                Flat
            </ToggleGroupItem>
            <ToggleGroupItem value="some" className="px-3">
                Some
            </ToggleGroupItem>
            <ToggleGroupItem value="hilly" className="px-3">
                Hilly
            </ToggleGroupItem>
        </ToggleGroup>
    ),
} satisfies Meta<typeof ToggleGroup>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Outline: Story = {
    play: async ({ args, canvas, userEvent }) => {
        const group = canvas.getByRole('radiogroup', { name: 'Hills' });

        await expect(group).toBeInTheDocument();
        await expect(canvas.getByRole('radio', { name: 'Some' })).toBeChecked();

        await userEvent.click(canvas.getByRole('radio', { name: 'Hilly' }));
        await expect(
            canvas.getByRole('radio', { name: 'Hilly' }),
        ).toBeChecked();
        await expect(
            canvas.getByRole('radio', { name: 'Some' }),
        ).not.toBeChecked();
        await expect(args.onValueChange).toHaveBeenLastCalledWith('hilly');
    },
};

/** From the keyboard: Tab lands on the choice that is on, the arrow keys move, Space picks. */
export const Plain: Story = {
    args: { variant: 'default' },
    play: async ({ args, canvas, userEvent }) => {
        await userEvent.tab();
        await expect(canvas.getByRole('radio', { name: 'Some' })).toHaveFocus();
        await userEvent.keyboard('{ArrowLeft}');
        await expect(canvas.getByRole('radio', { name: 'Flat' })).toHaveFocus();
        await userEvent.keyboard(' ');
        await expect(canvas.getByRole('radio', { name: 'Flat' })).toBeChecked();
        await expect(args.onValueChange).toHaveBeenLastCalledWith('flat');
    },
};

export const Small: Story = {
    args: { size: 'sm' },
    play: async ({ canvas }) => {
        for (const name of ['Flat', 'Some', 'Hilly']) {
            const height = canvas
                .getByRole('radio', { name })
                .getBoundingClientRect().height;

            // Still at least the 24px target.
            await expect(height).toBeGreaterThanOrEqual(24);
        }
    },
};
