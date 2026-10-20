import '../../../../css/settings-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect, within } from 'storybook/test';
import AvailableService from '@/components/connections/available-service';
import ConfirmEmailFirst from '@/components/connections/confirm-email-first';
import { useAppearance } from '@/hooks/use-appearance';
import type { ConnectionDescription } from '@/types';

/** The Connections page around the list, in Paper or Ink with the toolbar, with the note above it while locked. */
function SettingsScene({
    locked,
    children,
}: {
    locked: boolean;
    children: ReactNode;
}) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            {locked ? (
                <div style={{ maxWidth: 640, marginBottom: 16 }}>
                    <ConfirmEmailFirst id="confirm-email-first" sent={false} />
                </div>
            ) : null}
            <ul className="ai-apps" style={{ maxWidth: 640 }}>
                {children}
            </ul>
        </div>
    );
}

const wahoo: ConnectionDescription = {
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
    connected: false,
};

/**
 * A service the rider has not connected: what it is for, what it does, what Traceway keeps, and a way to connect
 * it. Connect waits while the rider has not confirmed their email.
 */
const meta = {
    title: 'Components/Connections/AvailableService',
    component: AvailableService,
    args: { service: wahoo },
    argTypes: {
        service: {
            control: 'object',
            description:
                'The service: its name and purpose, a note, what it does (with what comes later), and what Traceway keeps.',
        },
        locked: {
            control: 'boolean',
            description:
                'True while the rider cannot connect yet, such as before they confirm their email.',
        },
        lockedBy: {
            control: 'text',
            description: 'The id of the sentence that says why.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story, { args }) => (
            <SettingsScene locked={args.locked ?? false}>
                <Story />
            </SettingsScene>
        ),
    ],
} satisfies Meta<typeof AvailableService>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Named by a heading; what it does and what Traceway keeps are two named lists; Connect is a link to Wahoo. */
export const Wahoo: Story = {
    play: async ({ canvas, userEvent }) => {
        await expect(
            canvas.getByRole('heading', { level: 4, name: 'Wahoo' }),
        ).toBeVisible();

        const does = canvas.getByRole('list', { name: 'What it does' });
        await expect(within(does).getAllByRole('listitem')).toHaveLength(3);
        await expect(within(does).getByText('Later')).toBeVisible();

        const keeps = canvas.getByRole('list', { name: 'What Traceway keeps' });
        await expect(within(keeps).getAllByRole('listitem')).toHaveLength(2);

        // Connect is the only stop for the keyboard.
        await userEvent.tab();
        await expect(
            canvas.getByRole('link', { name: 'Connect Wahoo' }),
        ).toHaveFocus();
    },
};

export const OpenStreetMap: Story = {
    args: {
        service: {
            slug: 'openstreetmap',
            label: 'OpenStreetMap',
            group: 'maps',
            group_label: 'Maps',
            purpose: 'Log in with your mapping account.',
            note: 'Map notes you leave on a route come later.',
            keeps: ['Your OpenStreetMap user id, to log you in.'],
            capabilities: [
                { key: 'sign_in', label: 'Log in', later: false },
                { key: 'map_notes', label: 'Map notes', later: true },
            ],
            available: true,
            connected: false,
        },
    },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByText('Map notes you leave on a route come later.'),
        ).toBeVisible();
        await expect(
            canvas.getByRole('link', { name: 'Connect OpenStreetMap' }),
        ).toHaveAttribute(
            'href',
            '/settings/connections/openstreetmap/connect',
        );
    },
};

/** Before the rider confirms their email: Connect is a button that is off, and points at why. */
export const Locked: Story = {
    args: { locked: true, lockedBy: 'confirm-email-first' },
    play: async ({ canvas }) => {
        const button = canvas.getByRole('button', { name: 'Connect Wahoo' });

        await expect(button).toBeDisabled();
        await expect(button).toHaveAccessibleDescription(
            'Confirm your email first. Then you can add or remove a connection.',
        );
        await expect(canvas.queryByRole('link')).toBeNull();
    },
};
