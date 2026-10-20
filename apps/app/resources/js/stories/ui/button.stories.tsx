import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { Download, Plus } from 'lucide-react';
import { expect, fn } from 'storybook/test';
import { Button } from '@/components/ui/button';

/**
 * The button on sign-in, settings and admin screens. `default` fills with the marker (`primary`) for the step that
 * completes the form; everything else is quieter. Labels say what happens: "Save", "Add a passkey".
 */
const meta = {
    title: 'UI/Button',
    component: Button,
    args: { children: 'Save', onClick: fn() },
    argTypes: {
        variant: {
            control: 'inline-radio',
            options: [
                'default',
                'secondary',
                'outline',
                'ghost',
                'destructive',
                'link',
            ],
            description:
                '`default` fills with the marker for the step that completes a form. `destructive` only for something that cannot be undone.',
            table: { defaultValue: { summary: 'default' } },
        },
        size: {
            control: 'inline-radio',
            options: ['default', 'sm', 'lg', 'icon'],
            description:
                '`icon` is a square for one icon, and then needs an `aria-label`.',
            table: { defaultValue: { summary: 'default' } },
        },
        children: {
            control: 'text',
            description:
                'What happens when it is pressed: "Save", "Add a passkey".',
        },
        disabled: {
            control: 'boolean',
            description: 'Off while the form is sending or cannot be sent.',
        },
        asChild: {
            control: 'boolean',
            description:
                'Render the child element (a link) with button styles.',
        },
        type: {
            control: 'inline-radio',
            options: ['button', 'submit', 'reset'],
            description: 'The HTML button type. `submit` inside a form.',
        },
        onClick: {
            action: 'clicked',
            description:
                'Called when the rider presses it, by pointer or keyboard.',
        },
    },
} satisfies Meta<typeof Button>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ args, canvas, userEvent }) => {
        const button = canvas.getByRole('button', { name: 'Save' });

        await userEvent.click(button);
        await expect(args.onClick).toHaveBeenCalledTimes(1);

        // The keyboard presses it too: Tab to it, then Enter and Space.
        button.blur();
        await userEvent.tab();
        await expect(button).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await userEvent.keyboard(' ');
        await expect(args.onClick).toHaveBeenCalledTimes(3);
    },
};

export const Variants: Story = {
    render: (args) => (
        <div className="flex flex-wrap items-center gap-3">
            <Button {...args}>Save</Button>
            <Button {...args} variant="secondary">
                Download codes
            </Button>
            <Button {...args} variant="outline">
                Add a passkey
            </Button>
            <Button {...args} variant="ghost">
                Skip
            </Button>
            <Button {...args} variant="destructive">
                Delete account
            </Button>
        </div>
    ),
    play: async ({ canvas, userEvent }) => {
        // Focus moves through them in the order they read.
        for (const name of [
            'Save',
            'Download codes',
            'Add a passkey',
            'Skip',
            'Delete account',
        ]) {
            await userEvent.tab();
            await expect(canvas.getByRole('button', { name })).toHaveFocus();
        }
    },
};

export const Sizes: Story = {
    render: (args) => (
        <div className="flex flex-wrap items-center gap-3">
            <Button {...args} size="sm">
                Small
            </Button>
            <Button {...args}>Default</Button>
            <Button {...args} size="lg">
                Large
            </Button>
            <Button {...args} size="icon" aria-label="Add a route">
                <Plus />
            </Button>
        </div>
    ),
    play: async ({ args, canvas, userEvent }) => {
        const icon = canvas.getByRole('button', { name: 'Add a route' });
        const { width, height } = icon.getBoundingClientRect();

        // At least the 24px target, and square.
        await expect(width).toBeGreaterThanOrEqual(24);
        await expect(width).toBe(height);
        await userEvent.click(icon);
        await expect(args.onClick).toHaveBeenCalledOnce();
    },
};

export const WithIcon: Story = {
    args: {
        variant: 'outline',
        children: (
            <>
                <Download />
                GPX file
            </>
        ),
    },
    play: async ({ args, canvas, userEvent }) => {
        await userEvent.click(canvas.getByRole('button', { name: 'GPX file' }));
        await expect(args.onClick).toHaveBeenCalledOnce();
    },
};

export const Disabled: Story = {
    args: { disabled: true, children: 'Saving…' },
    play: async ({ args, canvas, userEvent }) => {
        const button = canvas.getByRole('button', { name: 'Saving…' });

        await expect(button).toBeDisabled();
        // A turned-off button is skipped by Tab and never fires.
        await userEvent.tab();
        await expect(button).not.toHaveFocus();
        await expect(args.onClick).not.toHaveBeenCalled();
    },
};

/** Something that cannot be undone asks first: Delete account opens the Dialog. */
export const AsksFirst: Story = {
    args: { variant: 'destructive', children: 'Delete account' },
    render: (args) => (
        <Button {...args} onClick={linkTo('UI/Dialog', 'Open')} />
    ),
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Delete account' }),
        ).toBeEnabled();
    },
};
