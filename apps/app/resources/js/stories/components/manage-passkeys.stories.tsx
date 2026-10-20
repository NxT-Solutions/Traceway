import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, screen, waitFor } from 'storybook/test';
import ManagePasskeys, {
    type Props as ManagePasskeysProps,
} from '@/components/manage-passkeys';
import { useAppearance } from '@/hooks/use-appearance';
import type { Passkey } from '@/types';
import { rider } from '../support/fixtures';
import { InertiaPage, sharedProps } from '../support/inertia-page';

const passkeys: Passkey[] = [
    {
        id: 1,
        name: 'MacBook Air',
        authenticator: 'iCloud Keychain',
        created_at_diff: '3 weeks ago',
        last_used_at_diff: '2 days ago',
    },
    {
        id: 2,
        name: 'Pixel 9',
        authenticator: 'Google Password Manager',
        created_at_diff: '1 week ago',
        last_used_at_diff: null,
    },
];

/** The Security page around the card, in Paper or Ink with the toolbar. */
function SecurityCard(props: ManagePasskeysProps) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div style={{ maxWidth: 640 }}>
                <ManagePasskeys {...props} />
            </div>
            <button
                type="button"
                className="mt-4 text-sm underline underline-offset-4"
                onClick={linkTo('Components/PasskeyItem', 'Default')}
            >
                One passkey on its own: PasskeyItem
            </button>
        </div>
    );
}

/** The card reads the server's errors, so it renders inside Inertia's page. */
function Passkeys({
    error,
    ...props
}: ManagePasskeysProps & { error?: string }) {
    return (
        <InertiaPage
            name="dashboard"
            component={SecurityCard}
            props={props}
            shared={{
                ...sharedProps({ user: rider, can: null }),
                errors: error === undefined ? {} : { passkey: error },
            }}
        />
    );
}

/**
 * The passkeys card on Security: every passkey, a way to add one, and why the last one cannot go while it is the
 * only way to log in. Nothing at all where passkeys are off.
 */
const meta = {
    title: 'Components/ManagePasskeys',
    component: Passkeys,
    args: { canManagePasskeys: true, passkeys },
    argTypes: {
        canManagePasskeys: {
            control: 'boolean',
            description: 'Passkeys are on for this site. Off draws nothing.',
        },
        passkeys: {
            control: 'object',
            description:
                "The rider's passkeys: name, where it lives, when it was added and last used.",
        },
        passkeyIsLastWayIn: {
            control: 'boolean',
            description:
                'No password and no connected account: the one passkey has to stay.',
        },
        passwordCard: {
            control: 'text',
            description:
                'The id of the password card on the same page, which the last-way-in sentence links to.',
        },
        error: {
            control: 'text',
            description: 'Why the server refused to remove a passkey.',
        },
    },
    parameters: { layout: 'fullscreen' },
} satisfies Meta<typeof Passkeys>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Each passkey is listed; Remove asks first, and Escape keeps the passkey. */
export const TwoPasskeys: Story = {
    play: async ({ canvas, userEvent, step }) => {
        await expect(
            canvas.getByRole('heading', { name: 'Passkeys' }),
        ).toBeVisible();
        await expect(canvas.getByText('MacBook Air')).toBeVisible();
        await expect(canvas.getByText('Pixel 9')).toBeVisible();

        await step('Ask to remove the MacBook Air passkey', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Remove passkey MacBook Air',
                }),
            );
            const dialog = await screen.findByRole('dialog', {
                name: 'Remove passkey',
            });

            await waitFor(() => expect(dialog).toBeVisible());
        });

        await step('Escape keeps it', async () => {
            await userEvent.keyboard('{Escape}');
            await waitFor(() =>
                expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
            );
            await expect(canvas.getByText('MacBook Air')).toBeVisible();
        });
    },
};

/** An empty card, and Add passkey opens the form with a name for it. */
export const NoPasskeys: Story = {
    args: { passkeys: [] },
    play: async ({ canvas, userEvent }) => {
        await expect(canvas.getByText('No passkeys yet')).toBeVisible();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Add passkey' }),
        );
        await expect(canvas.getByRole('textbox')).toBeVisible();
    },
};

/** One passkey, no password, no connected account: it has to stay. */
export const LastWayIn: Story = {
    args: {
        passkeys: passkeys.slice(0, 1),
        passkeyIsLastWayIn: true,
        passwordCard: 'password',
    },
    play: async ({ canvas }) => {
        const remove = canvas.getByRole('button', {
            name: 'Remove passkey MacBook Air',
        });

        await expect(remove).toBeDisabled();
        await expect(remove).toHaveAccessibleDescription(/only way to log in/i);
        await expect(
            canvas.getByRole('link', { name: 'Set a password' }),
        ).toHaveAttribute('href', '#password');
    },
};

/** The server refused to remove it: said as an alert, under the list. */
export const Refused: Story = {
    args: {
        error: 'That passkey is your only way to log in. Set a password first.',
    },
    play: async ({ canvas, args }) => {
        await expect(canvas.getByRole('alert')).toHaveTextContent(
            args.error ?? '',
        );
    },
};

/** Passkeys are off: nothing at all. */
export const Off: Story = {
    args: { canManagePasskeys: false },
    play: async ({ canvas }) => {
        await expect(
            canvas.queryByRole('heading', { name: 'Passkeys' }),
        ).not.toBeInTheDocument();
    },
};
