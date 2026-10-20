import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * One yes or no, always with a label the rider can click. "Remember me" on log in is the common one.
 */
const meta = {
    title: 'UI/Checkbox',
    component: Checkbox,
    args: { id: 'remember', onCheckedChange: fn() },
    argTypes: {
        id: {
            control: 'text',
            description: 'Ties the box to its label (`htmlFor`).',
        },
        checked: {
            control: 'boolean',
            description:
                'Controlled state. Leave unset to let the box keep its own.',
        },
        defaultChecked: {
            control: 'boolean',
            description: 'The state it starts in.',
        },
        disabled: {
            control: 'boolean',
            description: 'Turned off; its label fades with it.',
        },
        required: {
            control: 'boolean',
            description: 'The form cannot be sent until it is ticked.',
        },
        'aria-invalid': {
            control: 'boolean',
            description: 'Set when the server says it must be ticked.',
        },
        onCheckedChange: {
            action: 'checked changed',
            description: 'Called with the new state, `true` or `false`.',
        },
    },
    render: (args) => (
        <div className="flex items-center gap-3">
            <Checkbox {...args} />
            <Label htmlFor={args.id}>Remember me</Label>
        </div>
    ),
} satisfies Meta<typeof Checkbox>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ args, canvas, userEvent }) => {
        const box = canvas.getByRole('checkbox', { name: 'Remember me' });

        await expect(box).not.toBeChecked();
        // A click on the words ticks it.
        await userEvent.click(canvas.getByText('Remember me'));
        await expect(box).toBeChecked();
        await expect(args.onCheckedChange).toHaveBeenLastCalledWith(true);
        // Space, from the keyboard, unticks it.
        await expect(box).toHaveFocus();
        await userEvent.keyboard(' ');
        await expect(box).not.toBeChecked();
        await expect(args.onCheckedChange).toHaveBeenLastCalledWith(false);
    },
};

export const Checked: Story = {
    args: { defaultChecked: true },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('checkbox', { name: 'Remember me' }),
        ).toBeChecked();
    },
};

export const Disabled: Story = {
    args: { id: 'quiet-roads', disabled: true },
    render: (args) => (
        <div className="flex items-center gap-3">
            <Checkbox {...args} />
            <Label htmlFor={args.id}>Quiet roads only</Label>
        </div>
    ),
    play: async ({ args, canvas }) => {
        const box = canvas.getByRole('checkbox', { name: 'Quiet roads only' });

        await expect(box).toBeDisabled();
        await expect(box).not.toBeChecked();
        await expect(args.onCheckedChange).not.toHaveBeenCalled();
    },
};

export const Invalid: Story = {
    args: { id: 'terms', 'aria-invalid': true },
    render: (args) => (
        <div className="flex items-center gap-3">
            <Checkbox {...args} />
            <Label htmlFor={args.id}>I accept the terms of use</Label>
        </div>
    ),
    play: async ({ args, canvas, userEvent }) => {
        const box = canvas.getByRole('checkbox', {
            name: 'I accept the terms of use',
        });

        await expect(box).toBeInvalid();
        await userEvent.click(box);
        await expect(box).toBeChecked();
        await expect(args.onCheckedChange).toHaveBeenCalledWith(true);
    },
};
