import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';

/**
 * A password field with an eye that shows or hides what was typed. The eye says "Show password" or "Hide
 * password" to screen readers, and Tab reaches it after the field.
 */
const meta = {
    title: 'Components/PasswordInput',
    component: PasswordInput,
    args: {
        id: 'password',
        name: 'password',
        autoComplete: 'current-password',
        placeholder: 'Password',
        onChange: fn(),
    },
    argTypes: {
        id: { control: 'text', description: 'Ties the field to its label.' },
        name: { control: 'text', description: 'The form field name.' },
        autoComplete: {
            control: 'select',
            options: ['current-password', 'new-password'],
            description:
                '`current-password` to log in, `new-password` to set one, so the password manager helps.',
        },
        placeholder: { control: 'text' },
        defaultValue: {
            control: 'text',
            description: 'What is typed already.',
        },
        disabled: { control: 'boolean' },
        'aria-invalid': {
            control: 'boolean',
            description: 'The server said the password is wrong.',
        },
        'aria-describedby': {
            control: 'text',
            description: 'The id of the error under the field.',
        },
        onChange: { description: 'Every key the rider types.' },
    },
    render: (args) => (
        <div className="grid max-w-sm gap-2">
            <Label htmlFor={args.id}>Password</Label>
            <PasswordInput {...args} />
        </div>
    ),
} satisfies Meta<typeof PasswordInput>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Typing stays hidden until the eye shows it; the eye hides it again. */
export const Empty: Story = {
    play: async ({ canvas, args, userEvent, step }) => {
        const field = canvas.getByLabelText('Password');

        await step('Type a password', async () => {
            await userEvent.type(field, 'westerlo-loop-41');
            await expect(field).toHaveValue('westerlo-loop-41');
            await expect(field).toHaveAttribute('type', 'password');
            await expect(args.onChange).toHaveBeenCalled();
        });

        await step('Show it with the keyboard', async () => {
            await userEvent.tab();
            const show = canvas.getByRole('button', { name: 'Show password' });

            await expect(show).toHaveFocus();
            await userEvent.keyboard('{Enter}');
            await expect(field).toHaveAttribute('type', 'text');
        });

        await step('Hide it again', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Hide password' }),
            );
            await expect(field).toHaveAttribute('type', 'password');
        });
    },
};

export const Filled: Story = {
    args: { defaultValue: 'westerlo-loop-41' },
    play: async ({ canvas }) => {
        await expect(canvas.getByLabelText('Password')).toHaveValue(
            'westerlo-loop-41',
        );
    },
};

/** Wrong password: the field says so, and points at the message under it. */
export const Invalid: Story = {
    args: {
        defaultValue: 'short',
        'aria-invalid': true,
        'aria-describedby': 'password-error',
    },
    render: (args) => (
        <div className="grid max-w-sm gap-2">
            <Label htmlFor={args.id}>Password</Label>
            <PasswordInput {...args} />
            <InputError
                id="password-error"
                message="That password is not right. Try again."
            />
        </div>
    ),
    play: async ({ canvas }) => {
        const field = canvas.getByLabelText('Password');

        await expect(field).toBeInvalid();
        await expect(field).toHaveAccessibleDescription(
            'That password is not right. Try again.',
        );
    },
};
