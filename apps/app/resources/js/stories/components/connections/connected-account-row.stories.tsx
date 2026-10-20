import '../../../../css/settings-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { MouseEvent, ReactNode } from 'react';
import { expect, screen, waitFor, within } from 'storybook/test';
import ConnectedAccountRow from '@/components/connections/connected-account-row';
import { useAppearance } from '@/hooks/use-appearance';
import type { ConnectedAccount } from '@/types';
import { goToStory } from '../../support/stay-in-story';

const toSecurity = goToStory('Pages/Settings/Security', 'Two Factor Off');

/** The Connections page around the list, in Paper or Ink with the toolbar. A Security link opens that page's story. */
function SettingsScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    function follow(event: MouseEvent) {
        if ((event.target as Element).closest('a.cx-link') !== null) {
            toSecurity(event);
        }
    }

    return (
        <div
            className="tw-settings"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
            onClickCapture={follow}
        >
            <ul className="cx-list" style={{ maxWidth: 640 }}>
                {children}
            </ul>
        </div>
    );
}

const wahoo: ConnectedAccount = {
    slug: 'wahoo',
    label: 'Wahoo',
    group: 'sport',
    group_label: 'Sport',
    purpose: 'Send routes to your Elemnt.',
    note: null,
    keeps: [
        'Your Wahoo user id, to log you in.',
        'A key from Wahoo to send routes, until you disconnect.',
    ],
    capabilities: [
        { key: 'sign_in', label: 'Log in', later: false },
        { key: 'send_routes', label: 'Send routes', later: false },
        { key: 'import_routes', label: 'Import routes', later: true },
    ],
    available: true,
    connected: true,
    account_label: 'sam@example.com',
    needs_reconnect: false,
    connected_at: '2026-10-02T07:30:00Z',
    last_signed_in_at: '2026-10-18T06:12:00Z',
    last_sent_at: '2026-10-19T06:45:00Z',
    manage_url: null,
    withdraws_access: true,
    can_disconnect: true,
    disconnect_blocked: null,
};

/**
 * One connected account: who it is at the service, what it does now, when it was last used, and how to connect it
 * again or disconnect it. When it is the rider's last way to log in, Disconnect is off and says where to add
 * another way.
 */
const meta = {
    title: 'Components/Connections/ConnectedAccountRow',
    component: ConnectedAccountRow,
    args: { account: wahoo, hasPassword: true },
    argTypes: {
        account: {
            control: 'object',
            description:
                'The connected account: who it is at the service, what it does, when it was last used, and whether it can go.',
        },
        hasPassword: {
            control: 'boolean',
            description:
                'Whether the rider has a password, which changes where a blocked Disconnect sends them.',
        },
        focusAfter: {
            control: 'object',
            description:
                'Ids of what takes focus once this row is disconnected and gone.',
        },
        locked: {
            control: 'boolean',
            description:
                'True while the rider cannot connect or disconnect, such as before they confirm their email.',
        },
        lockedBy: {
            control: 'text',
            description: 'The id of the sentence that says why.',
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
} satisfies Meta<typeof ConnectedAccountRow>;

export default meta;

type Story = StoryObj<typeof meta>;

/**
 * Who it is at Wahoo and what it does today (Later is left out). Disconnect asks first; Escape keeps the account
 * and puts focus back on the button.
 */
export const Wahoo: Story = {
    play: async ({ canvas, userEvent }) => {
        await expect(
            canvas.getByRole('heading', { level: 3, name: 'Wahoo' }),
        ).toBeVisible();
        await expect(canvas.getByText('sam@example.com')).toBeVisible();

        const does = canvas.getByRole('list', { name: 'What Wahoo does' });
        await expect(within(does).getAllByRole('listitem')).toHaveLength(2);
        await expect(within(does).queryByText('Later')).toBeNull();

        const disconnect = canvas.getByRole('button', {
            name: 'Disconnect Wahoo',
        });
        await userEvent.click(disconnect);
        const dialog = await screen.findByRole('dialog', {
            name: 'Disconnect Wahoo?',
        });
        // The dialog fades in.
        await waitFor(() => expect(dialog).toBeVisible());

        await userEvent.keyboard('{Escape}');
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        await expect(disconnect).toHaveFocus();
    },
};

/** Traceway lost its link: connect it again. */
export const NeedsReconnect: Story = {
    args: {
        account: {
            ...wahoo,
            slug: 'ride_with_gps',
            label: 'Ride with GPS',
            account_label: 'Sam R.',
            needs_reconnect: true,
            last_signed_in_at: null,
        },
    },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByText(
                'Traceway lost its link to Ride with GPS. Connect it again.',
            ),
        ).toBeVisible();
        await expect(
            canvas.getByRole('link', { name: 'Connect Ride with GPS again' }),
        ).toBeVisible();
        await expect(
            canvas.getByRole('button', { name: 'Disconnect Ride with GPS' }),
        ).toBeEnabled();
    },
};

/** The last way to log in: Disconnect is off and says why. */
export const LastWayIn: Story = {
    args: {
        hasPassword: false,
        account: {
            ...wahoo,
            slug: 'google',
            label: 'Google',
            capabilities: [{ key: 'sign_in', label: 'Log in', later: false }],
            last_sent_at: null,
            can_disconnect: false,
            disconnect_blocked: 'Google is your only way to log in.',
        },
    },
    play: async ({ canvas }) => {
        const disconnect = canvas.getByRole('button', {
            name: 'Disconnect Google',
        });

        await expect(disconnect).toBeDisabled();
        await expect(disconnect).toHaveAccessibleDescription(
            'Google is your only way to log in. Set a password or passkey in Security',
        );
        await expect(
            canvas.getByRole('link', {
                name: 'Set a password or passkey in Security',
            }),
        ).toHaveAttribute('href', '/settings/security');
    },
};

/** This Traceway no longer has the service set up; it can still go. */
export const NoLongerAvailable: Story = {
    args: {
        account: {
            ...wahoo,
            slug: 'discord',
            label: 'Discord',
            account_label: 'samrides',
            capabilities: [{ key: 'sign_in', label: 'Log in', later: false }],
            available: false,
            withdraws_access: false,
            manage_url: 'https://discord.com/settings/authorized-apps',
            last_sent_at: null,
        },
    },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByText(
                'Discord is not available on Traceway right now. You can still disconnect it.',
            ),
        ).toBeVisible();
        await expect(
            canvas.getByRole('button', { name: 'Disconnect Discord' }),
        ).toBeEnabled();
    },
};

/** Before the rider confirms their email: Disconnect is off. */
export const Locked: Story = {
    args: { locked: true },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Disconnect Wahoo' }),
        ).toBeDisabled();
    },
};
