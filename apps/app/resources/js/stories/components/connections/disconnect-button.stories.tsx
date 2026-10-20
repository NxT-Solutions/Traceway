import '../../../../css/settings-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { MouseEvent, ReactNode } from 'react';
import { expect, screen, waitFor, within } from 'storybook/test';
import DisconnectBlocked from '@/components/connections/disconnect-blocked';
import DisconnectButton from '@/components/connections/disconnect-button';
import { useAppearance } from '@/hooks/use-appearance';
import { goToStory } from '../../support/stay-in-story';

const toSecurity = goToStory('Pages/Settings/Security', 'Two Factor Off');

/** The Connections page around the button, in Paper or Ink with the toolbar. A Security link opens that page's story. */
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
            <div className="cx-buttons">{children}</div>
        </div>
    );
}

/**
 * Disconnects one service after saying what happens: no more logging in with it, and what Traceway asks the
 * service to do or leaves to the rider. Once it is gone, focus moves to the place the page names.
 */
const meta = {
    title: 'Components/Connections/DisconnectButton',
    component: DisconnectButton,
    args: {
        slug: 'wahoo',
        label: 'Wahoo',
        withdrawsAccess: true,
        sendsRoutes: true,
        manageUrl: null,
    },
    argTypes: {
        slug: {
            control: 'select',
            options: [
                'google',
                'github',
                'discord',
                'wahoo',
                'ride_with_gps',
                'runsignup',
                'openstreetmap',
            ],
            description: 'The service to disconnect.',
        },
        label: {
            control: 'text',
            description: 'The service as riders know it: Disconnect {label}.',
        },
        withdrawsAccess: {
            control: 'boolean',
            description:
                'True when Traceway holds keys from the service and asks it to withdraw them. The dialog says so.',
        },
        sendsRoutes: {
            control: 'boolean',
            description:
                'True when the connection sends routes today. The dialog says sent routes stay at the service.',
        },
        manageUrl: {
            control: 'text',
            description:
                'Where the rider removes Traceway at the service, when Traceway cannot. Opens in a new tab.',
        },
        disabled: {
            control: 'boolean',
            description:
                'Off, such as for the last way to log in or before the email is confirmed.',
        },
        describedBy: {
            control: 'text',
            description: 'The id of the sentence that says why it is off.',
        },
        focusAfter: {
            control: 'object',
            description:
                'Ids of what takes focus once the service is gone and this button with it. The first one on the page wins.',
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
} satisfies Meta<typeof DisconnectButton>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvas, userEvent }) => {
        await userEvent.tab();
        await expect(
            canvas.getByRole('button', { name: 'Disconnect Wahoo' }),
        ).toHaveFocus();
        await expect(screen.queryByRole('dialog')).toBeNull();
    },
};

/** Wahoo: Traceway asks it to remove its access, and sent routes stay in the rider's library there. */
export const Asking: Story = {
    play: async ({ canvas, userEvent }) => {
        await userEvent.click(
            canvas.getByRole('button', { name: 'Disconnect Wahoo' }),
        );

        const dialog = await screen.findByRole('dialog', {
            name: 'Disconnect Wahoo?',
        });
        await expect(dialog).toHaveAccessibleDescription(
            'You can no longer log in with Wahoo. Traceway also asks Wahoo to remove its access. Routes you sent stay in your Wahoo library, but Traceway forgets them.',
        );
        // The dialog fades in.
        await waitFor(() =>
            expect(
                within(dialog).getByRole('button', { name: 'Cancel' }),
            ).toBeVisible(),
        );
        await expect(
            within(dialog).getByRole('button', { name: 'Disconnect Wahoo' }),
        ).toBeEnabled();
    },
};

/** Opened from the keyboard and left with Escape: nothing changes, and focus is back on the button. */
export const Cancelled: Story = {
    play: async ({ canvas, userEvent }) => {
        const button = canvas.getByRole('button', { name: 'Disconnect Wahoo' });

        await userEvent.tab();
        await userEvent.keyboard('{Enter}');
        const dialog = await screen.findByRole('dialog');
        await waitFor(() => expect(dialog).toBeVisible());

        await userEvent.keyboard('{Escape}');
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        await expect(button).toHaveFocus();
    },
};

/** GitHub: Traceway cannot remove itself, so the dialog links to where the rider can, in a new tab. */
export const RemoveAtTheService: Story = {
    args: {
        slug: 'github',
        label: 'GitHub',
        withdrawsAccess: false,
        sendsRoutes: false,
        manageUrl: 'https://github.com/settings/applications',
    },
    play: async ({ canvas, userEvent }) => {
        await userEvent.click(
            canvas.getByRole('button', { name: 'Disconnect GitHub' }),
        );

        const dialog = await screen.findByRole('dialog', {
            name: 'Disconnect GitHub?',
        });
        const link = within(dialog).getByRole('link', {
            name: 'Remove Traceway at GitHub too (opens in a new tab)',
        });

        await expect(link).toHaveAttribute(
            'href',
            'https://github.com/settings/applications',
        );
        await expect(link).toHaveAttribute('target', '_blank');
        await expect(dialog).not.toHaveTextContent('asks GitHub');
    },
};

/** Off, and described by the reason beside it, with a way to add another way to log in. */
export const Disabled: Story = {
    args: {
        slug: 'google',
        label: 'Google',
        disabled: true,
        describedBy: 'google-blocked',
    },
    render: (args) => (
        <div>
            <DisconnectBlocked
                id="google-blocked"
                message="Google is your only way to log in."
            />
            <DisconnectButton {...args} />
        </div>
    ),
    play: async ({ canvas }) => {
        const button = canvas.getByRole('button', {
            name: 'Disconnect Google',
        });

        await expect(button).toBeDisabled();
        await expect(button).toHaveAccessibleDescription(
            'Google is your only way to log in. Set a password or passkey in Security',
        );
    },
};
