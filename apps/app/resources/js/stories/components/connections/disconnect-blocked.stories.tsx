import '../../../../css/settings-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { MouseEvent, ReactNode } from 'react';
import { expect } from 'storybook/test';
import DisconnectBlocked from '@/components/connections/disconnect-blocked';
import { useAppearance } from '@/hooks/use-appearance';
import { goToStory } from '../../support/stay-in-story';

const toSecurity = goToStory('Pages/Settings/Security', 'Two Factor Off');

/** The Connections page around the note, in Paper or Ink with the toolbar. Its Security link opens that page's story. */
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
            <div style={{ maxWidth: 640 }}>{children}</div>
        </div>
    );
}

/**
 * Why a service cannot be disconnected yet, such as it being the rider's last way to log in, and where to add
 * another way first. The link opens Security.
 */
const meta = {
    title: 'Components/Connections/DisconnectBlocked',
    component: DisconnectBlocked,
    args: {
        id: 'google-blocked',
        message: 'Google is your only way to log in.',
        hasPassword: false,
    },
    argTypes: {
        id: {
            control: 'text',
            description:
                'The id the Disconnect button points to for its description.',
        },
        message: {
            control: 'text',
            description: 'Why the service cannot go yet, in one sentence.',
        },
        hasPassword: {
            control: 'boolean',
            description:
                'With a password, the way out is a passkey; without one, a password or a passkey.',
        },
        className: {
            control: 'text',
            description: 'Extra classes beside cx-blocked.',
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
} satisfies Meta<typeof DisconnectBlocked>;

export default meta;

type Story = StoryObj<typeof meta>;

/** No password yet: set one or add a passkey. The sentence carries the id the button points to. */
export const NoPassword: Story = {
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        await expect(
            canvasElement.querySelector(`#${args.id}`),
        ).toHaveTextContent(args.message);

        const link = canvas.getByRole('link', {
            name: 'Set a password or passkey in Security',
        });
        await expect(link).toHaveAttribute('href', '/settings/security');
        await userEvent.tab();
        await expect(link).toHaveFocus();
    },
};

/** With a password, the way out is a passkey. */
export const HasPassword: Story = {
    args: {
        message: 'Wahoo still sends your routes to the Elemnt.',
        hasPassword: true,
    },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('link', { name: 'Add a passkey in Security' }),
        ).toHaveAttribute('href', '/settings/security');
    },
};
