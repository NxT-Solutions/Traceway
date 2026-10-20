import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSeparator,
    InputOTPSlot,
} from '@/components/ui/input-otp';

/**
 * The six digits from an authenticator app, one box each. Typing or pasting fills them in order.
 */
const meta = {
    title: 'UI/InputOTP',
    component: InputOTP,
    args: {
        maxLength: 6,
        'aria-label': 'Code from your authenticator app',
        onChange: fn(),
        onComplete: fn(),
        children: (
            <>
                <InputOTPGroup>
                    <InputOTPSlot index={0} />
                    <InputOTPSlot index={1} />
                    <InputOTPSlot index={2} />
                </InputOTPGroup>
                <InputOTPSeparator />
                <InputOTPGroup>
                    <InputOTPSlot index={3} />
                    <InputOTPSlot index={4} />
                    <InputOTPSlot index={5} />
                </InputOTPGroup>
            </>
        ),
    },
    argTypes: {
        maxLength: {
            control: { type: 'number', min: 1, max: 8 },
            description:
                'How many digits the code has. Six for an authenticator app.',
        },
        'aria-label': {
            control: 'text',
            description: 'The name of the field, read by screen readers.',
        },
        defaultValue: {
            control: 'text',
            description: 'Digits already in the boxes.',
        },
        disabled: {
            control: 'boolean',
            description: 'Off while the code is checked.',
        },
        pattern: {
            control: 'text',
            description:
                'A regular expression each character must match, such as digits only.',
        },
        children: { control: false, description: 'The groups of slots.' },
        onChange: {
            action: 'changed',
            description: 'Called with the digits so far, on every key.',
        },
        onComplete: {
            action: 'completed',
            description: 'Called with the whole code once every box is filled.',
        },
    },
} satisfies Meta<typeof InputOTP>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Empty: Story = {
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('textbox', {
                name: 'Code from your authenticator app',
            }),
        ).toHaveValue('');
    },
};

/** The rider types the code: each digit fills the next box, and the whole code completes it. */
export const Typing: Story = {
    play: async ({ args, canvas, userEvent }) => {
        const field = canvas.getByRole('textbox', {
            name: 'Code from your authenticator app',
        });

        await userEvent.click(field);
        await userEvent.keyboard('482');
        await expect(args.onChange).toHaveBeenLastCalledWith('482');
        await expect(args.onComplete).not.toHaveBeenCalled();
        await userEvent.keyboard('913');
        await expect(field).toHaveValue('482913');
        await expect(args.onComplete).toHaveBeenCalledWith('482913');
        await expect(canvas.getByText('9')).toBeVisible();
    },
};

export const Filled: Story = {
    args: { defaultValue: '482913' },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('textbox', {
                name: 'Code from your authenticator app',
            }),
        ).toHaveValue('482913');
    },
};

export const Disabled: Story = {
    args: { defaultValue: '482', disabled: true },
    play: async ({ args, canvas }) => {
        await expect(
            canvas.getByRole('textbox', {
                name: 'Code from your authenticator app',
            }),
        ).toBeDisabled();
        await expect(args.onChange).not.toHaveBeenCalled();
    },
};
