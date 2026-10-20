import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect, screen, waitFor, within } from 'storybook/test';
import DeleteUser from '@/components/delete-user';
import { useAppearance } from '@/hooks/use-appearance';

/** The settings page around a card, in Paper or Ink with the toolbar. */
function SettingsScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div style={{ maxWidth: 640 }}>{children}</div>
        </div>
    );
}

/**
 * The last card of Profile: deleting the account and every route in it. Files already on a watch or GPS stay
 * there. The dialog asks for the password; a rider without one confirms it is them first, with a passkey or a
 * connected account.
 */
const meta = {
    title: 'Components/DeleteUser',
    component: DeleteUser,
    args: { hasPassword: true, confirmedRecently: false },
    argTypes: {
        hasPassword: {
            control: 'boolean',
            description: 'The rider has a password, so the dialog asks for it.',
        },
        confirmedRecently: {
            control: 'boolean',
            description:
                'A rider without a password confirmed it is them a moment ago, with a passkey or a connected account.',
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
} satisfies Meta<typeof DeleteUser>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The card says what goes and what stays. */
export const Default: Story = {
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('heading', { name: 'Delete account' }),
        ).toBeVisible();
        await expect(canvas.getByText('This cannot be undone.')).toBeVisible();
        await expect(
            canvas.getByRole('button', { name: 'Delete account' }),
        ).toBeEnabled();
    },
};

/** Opens the dialog and waits for it to finish opening. */
const openDialog = async ({
    canvas,
    userEvent,
}: Parameters<NonNullable<Story['play']>>[0]) => {
    await userEvent.click(
        canvas.getByRole('button', { name: 'Delete account' }),
    );

    const dialog = await screen.findByRole('dialog', {
        name: 'Are you sure you want to delete your account?',
    });

    await waitFor(() => expect(dialog).toBeVisible());

    return within(dialog);
};

/** The dialog, asking for the password, with Delete account last. */
export const Asking: Story = {
    play: async (context) => {
        const dialog = await openDialog(context);
        const password = dialog.getByLabelText('Password');

        await context.userEvent.type(password, 'westerlo-loop-41');
        await expect(password).toHaveValue('westerlo-loop-41');
        await expect(
            dialog.getByRole('button', { name: 'Delete account' }),
        ).toHaveAttribute('type', 'submit');
    },
};

/** Cancel keeps the account, and Escape does too. */
export const Cancelled: Story = {
    play: async (context) => {
        const dialog = await openDialog(context);

        await context.userEvent.click(
            dialog.getByRole('button', { name: 'Cancel' }),
        );
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );

        await openDialog(context);
        await context.userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await expect(
            context.canvas.getByRole('button', { name: 'Delete account' }),
        ).toHaveFocus();
    },
};

/** No password, not confirmed lately: the dialog sends the rider to confirm it is them. */
export const ConfirmFirst: Story = {
    args: { hasPassword: false },
    play: async (context) => {
        const dialog = await openDialog(context);

        await expect(
            dialog.queryByLabelText('Password'),
        ).not.toBeInTheDocument();
        await expect(
            dialog.getByRole('link', { name: 'Confirm it is you' }),
        ).toBeVisible();
        await expect(
            dialog.queryByRole('button', { name: 'Delete account' }),
        ).not.toBeInTheDocument();
    },
};

/** No password, confirmed a moment ago: one button deletes. */
export const ConfirmedRecently: Story = {
    args: { hasPassword: false, confirmedRecently: true },
    play: async (context) => {
        const dialog = await openDialog(context);

        await expect(
            dialog.queryByLabelText('Password'),
        ).not.toBeInTheDocument();
        await expect(
            dialog.getByRole('button', { name: 'Delete account' }),
        ).toBeEnabled();
    },
};
