import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * A one-line field, always with a label above it. A wrong value sets `aria-invalid` and says what to fix right
 * under the field.
 */
const meta = {
    title: 'UI/Input',
    component: Input,
    args: {
        id: 'route-name',
        placeholder: 'Morning loop',
        onChange: fn(),
    },
    argTypes: {
        id: {
            control: 'text',
            description: 'Ties the field to its label (`htmlFor`).',
        },
        type: {
            control: 'select',
            options: ['text', 'email', 'password', 'number', 'search', 'tel'],
            description: 'The kind of value, which picks the phone keyboard.',
            table: { defaultValue: { summary: 'text' } },
        },
        placeholder: {
            control: 'text',
            description: 'An example of the value. Never instead of a label.',
        },
        defaultValue: {
            control: 'text',
            description: 'The value it starts with.',
        },
        disabled: {
            control: 'boolean',
            description:
                'Turned off, such as an email that cannot change here.',
        },
        required: {
            control: 'boolean',
            description: 'The form cannot be sent while it is empty.',
        },
        'aria-invalid': {
            control: 'boolean',
            description:
                'Set when the server says the value is wrong; the border turns red.',
        },
        'aria-describedby': {
            control: 'text',
            description:
                'The id of the message under the field that says what to fix.',
        },
        onChange: {
            action: 'changed',
            description: 'Called on every key.',
        },
    },
    render: (args) => (
        <div className="grid max-w-sm gap-2">
            <Label htmlFor={args.id}>Route name</Label>
            <Input {...args} />
        </div>
    ),
} satisfies Meta<typeof Input>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ args, canvas, userEvent }) => {
        const field = canvas.getByRole('textbox', { name: 'Route name' });

        // A click on the label puts the cursor in the field.
        await userEvent.click(canvas.getByText('Route name'));
        await expect(field).toHaveFocus();
        await userEvent.keyboard('Westerlo gravel');
        await expect(field).toHaveValue('Westerlo gravel');
        await expect(args.onChange).toHaveBeenCalledTimes(15);
    },
};

export const Filled: Story = {
    args: { defaultValue: 'Westerlo gravel' },
    play: async ({ args, canvas, userEvent }) => {
        const field = canvas.getByRole('textbox', { name: 'Route name' });

        await expect(field).toHaveValue('Westerlo gravel');
        await userEvent.clear(field);
        await userEvent.type(field, 'Morning loop');
        await expect(field).toHaveValue('Morning loop');
        await expect(args.onChange).toHaveBeenCalled();
    },
};

export const Invalid: Story = {
    args: {
        id: 'device-name',
        defaultValue: 'My Forerunner 965 watch',
        'aria-invalid': true,
        'aria-describedby': 'device-name-error',
    },
    render: (args) => (
        <div className="grid max-w-sm gap-2">
            <Label htmlFor={args.id}>Device name</Label>
            <Input {...args} />
            <InputError
                id="device-name-error"
                message="A device name is 1 to 15 characters."
            />
        </div>
    ),
    play: async ({ canvas }) => {
        const field = canvas.getByRole('textbox', { name: 'Device name' });

        await expect(field).toBeInvalid();
        await expect(field).toHaveAccessibleDescription(
            'A device name is 1 to 15 characters.',
        );
    },
};

export const Disabled: Story = {
    args: {
        id: 'email',
        type: 'email',
        defaultValue: 'sam@example.com',
        disabled: true,
    },
    render: (args) => (
        <div className="grid max-w-sm gap-2">
            <Label htmlFor={args.id}>Email</Label>
            <Input {...args} />
        </div>
    ),
    play: async ({ args, canvas, userEvent }) => {
        const field = canvas.getByRole('textbox', { name: 'Email' });

        await expect(field).toBeDisabled();
        await userEvent.tab();
        await expect(field).not.toHaveFocus();
        await expect(args.onChange).not.toHaveBeenCalled();
    },
};
