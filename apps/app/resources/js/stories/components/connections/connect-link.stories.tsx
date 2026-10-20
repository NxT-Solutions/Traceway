import '../../../../css/settings-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { MouseEvent, ReactNode } from 'react';
import { expect } from 'storybook/test';
import ConnectLink from '@/components/connections/connect-link';
import { useAppearance } from '@/hooks/use-appearance';
import { goToStory, stayInStory } from '../../support/stay-in-story';

const backConnected = goToStory(
    'Components/Connections/ConnectedAccountRow',
    'Wahoo',
);

/**
 * The Connections page around the link, in Paper or Ink with the toolbar. The service's own page is not here, so
 * following the link opens the story of the account once it is connected.
 */
function SettingsScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    function follow(event: MouseEvent) {
        if ((event.target as Element).closest('a.cx-action') !== null) {
            backConnected(event);
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
 * Sends the rider to the service's own page to allow Traceway. A plain link, so the browser follows the redirect
 * off the site; while that page loads, a spinner sits beside the words. While the rider cannot connect yet, it is
 * a button that is off and points at why. In Storybook, following it opens the connected account.
 */
const meta = {
    title: 'Components/Connections/ConnectLink',
    component: ConnectLink,
    args: { slug: 'wahoo', label: 'Wahoo' },
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
            description: 'The service, in the address the link opens.',
        },
        label: {
            control: 'text',
            description: 'The service as riders know it: Connect {label}.',
        },
        again: {
            control: 'boolean',
            description:
                'True when Traceway lost its link to the service: Connect {label} again.',
        },
        returnTo: {
            control: 'text',
            description:
                'Where the rider lands after allowing Traceway at the service.',
        },
        disabled: {
            control: 'boolean',
            description:
                'True while the rider cannot connect yet, such as before they confirm their email. A button that is off.',
        },
        describedBy: {
            control: 'text',
            description: 'The id of the sentence that says why it is off.',
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
} satisfies Meta<typeof ConnectLink>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A link, not a button: the browser follows it to the service. */
export const Default: Story = {
    play: async ({ canvas, userEvent }) => {
        const link = canvas.getByRole('link', { name: 'Connect Wahoo' });

        await expect(link).toHaveAttribute(
            'href',
            '/settings/connections/wahoo/connect',
        );
        await userEvent.tab();
        await expect(link).toHaveFocus();
        await expect(link).not.toHaveAttribute('aria-busy');
    },
};

/** Clicked: while the service's page loads, the link is busy and a spinner sits beside the same words. */
export const Opening: Story = {
    play: async ({ canvas, canvasElement, userEvent }) => {
        const link = canvas.getByRole('link', { name: 'Connect Wahoo' });

        await stayInStory(() => userEvent.click(link));

        await expect(link).toHaveAttribute('aria-busy', 'true');
        await expect(link).toHaveTextContent('Connect Wahoo');
        await expect(canvasElement.querySelector('.cx-spin')).toBeVisible();
    },
};

/** Traceway lost its link to the service. */
export const Again: Story = {
    args: { slug: 'ride_with_gps', label: 'Ride with GPS', again: true },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('link', { name: 'Connect Ride with GPS again' }),
        ).toHaveAttribute(
            'href',
            '/settings/connections/ride_with_gps/connect',
        );
    },
};

/** Before the rider confirms their email: a button that is off, described by the reason. */
export const Disabled: Story = {
    args: { disabled: true, describedBy: 'confirm-email-first' },
    render: (args) => (
        <>
            <ConnectLink {...args} />
            <p className="cx-note" id="confirm-email-first">
                Confirm your email first. Then you can add or remove a
                connection.
            </p>
        </>
    ),
    play: async ({ canvas }) => {
        const button = canvas.getByRole('button', { name: 'Connect Wahoo' });

        await expect(button).toBeDisabled();
        await expect(button).toHaveAccessibleDescription(
            'Confirm your email first. Then you can add or remove a connection.',
        );
        await expect(canvas.queryByRole('link')).toBeNull();
    },
};
