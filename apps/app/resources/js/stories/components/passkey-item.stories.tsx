import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect, fn, screen, waitFor, within } from 'storybook/test';
import PasskeyItem from '@/components/passkey-item';
import { useAppearance } from '@/hooks/use-appearance';

/** The settings page around the list of passkeys, in Paper or Ink with the toolbar. */
function SettingsScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div
                className="overflow-hidden rounded-lg border border-border"
                style={{ maxWidth: 640 }}
            >
                {children}
            </div>
            <button
                type="button"
                className="mt-4 text-sm underline underline-offset-4"
                onClick={linkTo('Components/ManagePasskeys', 'Two Passkeys')}
            >
                The whole card: ManagePasskeys
            </button>
        </div>
    );
}

/**
 * One passkey: its name, where it lives, when it was added and last used, and a way to remove it after asking.
 * Removing is off while it is the rider's only way to log in.
 */
const meta = {
    title: 'Components/PasskeyItem',
    component: PasskeyItem,
    args: {
        passkey: {
            id: 1,
            name: 'MacBook Air',
            authenticator: 'iCloud Keychain',
            created_at_diff: '3 weeks ago',
            last_used_at_diff: '2 days ago',
        },
        onDelete: fn(),
    },
    argTypes: {
        passkey: {
            control: 'object',
            description:
                'The passkey: its name, where it lives (`authenticator`), when it was added and last used.',
        },
        onDelete: {
            description:
                'Remove was confirmed: the passkey id, and what to do if the server refuses.',
        },
        removeBlockedBy: {
            control: 'text',
            description:
                'The id of the sentence that says why this passkey cannot be removed. Removing is off while set.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <SettingsScene>
                <Story />
            </SettingsScene>
        ),
    ],
} satisfies Meta<typeof PasskeyItem>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Its name, where it lives, and when it was added and last used. */
export const Default: Story = {
    play: async ({ canvas }) => {
        await expect(canvas.getByText('MacBook Air')).toBeVisible();
        await expect(canvas.getByText('iCloud Keychain')).toBeVisible();
        await expect(canvas.getByText(/Added 3 weeks ago/)).toBeVisible();
        await expect(canvas.getByText(/Last used 2 days ago/)).toBeVisible();
    },
};

export const NeverUsed: Story = {
    args: {
        passkey: {
            id: 2,
            name: 'Pixel 9',
            authenticator: null,
            created_at_diff: '5 minutes ago',
            last_used_at_diff: null,
        },
    },
    play: async ({ canvas }) => {
        await expect(canvas.getByText(/Added 5 minutes ago/)).toBeVisible();
        await expect(canvas.queryByText(/Last used/)).not.toBeInTheDocument();
    },
};

/** The only way in: the remove button is off and points at why. */
export const LastWayIn: Story = {
    args: { removeBlockedBy: 'passkey-last-way-in' },
    play: async ({ canvas }) => {
        const remove = canvas.getByRole('button', {
            name: 'Remove passkey MacBook Air',
        });

        await expect(remove).toBeDisabled();
        await expect(remove).toHaveAttribute(
            'aria-describedby',
            'passkey-last-way-in',
        );
    },
};

/** Asking before it removes the passkey. */
export const Removing: Story = {
    play: async ({ canvas, userEvent }) => {
        await userEvent.click(
            canvas.getByRole('button', { name: /remove passkey/i }),
        );

        const dialog = await screen.findByRole('dialog', {
            name: 'Remove passkey',
        });

        await waitFor(() => expect(dialog).toBeVisible());
        await expect(dialog).toHaveTextContent('"MacBook Air"');
    },
};

/** Saying yes removes it, and the button says it is busy. */
export const Removed: Story = {
    play: async (context) => {
        await Removing.play?.(context);
        const { args, userEvent } = context;
        const dialog = within(screen.getByRole('dialog'));

        await userEvent.click(
            dialog.getByRole('button', { name: 'Remove passkey' }),
        );
        await expect(args.onDelete).toHaveBeenCalledWith(
            1,
            expect.any(Function),
        );
        await expect(
            dialog.getByRole('button', { name: 'Removing...' }),
        ).toBeDisabled();
    },
};
