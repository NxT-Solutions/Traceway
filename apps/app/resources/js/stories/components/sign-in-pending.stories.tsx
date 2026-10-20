import type { Meta, StoryObj } from '@storybook/react-vite';
import '../../../css/login-scene.css';
import { expect, waitFor } from 'storybook/test';
import PendingConnectionNotice from '@/components/sign-in-pending';

/**
 * A service waiting for the rider to log in. When the service confirmed an email that already has an account, it
 * connects once they log in to it. Otherwise they log in and then say yes, so nobody can take an account over by
 * naming its email at a service. "Not now" forgets it.
 */
const meta = {
    title: 'Components/SignInPending',
    component: PendingConnectionNotice,
    args: {
        pending: {
            slug: 'wahoo',
            label: 'Wahoo',
            email: 's•••@example.com',
            confirm: false,
        },
    },
    argTypes: {
        pending: {
            control: 'object',
            description:
                'The service waiting: its slug and name, the email it confirmed (masked) or null, and whether the rider confirms once in.',
        },
        focus: {
            control: 'boolean',
            description:
                'Take focus once the page is ready, instead of being a live note, so it is read once, before the form.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <div className="tw-login" style={{ minHeight: 0 }}>
                <div className="form" style={{ padding: 24 }}>
                    <Story />
                </div>
            </div>
        ),
    ],
} satisfies Meta<typeof PendingConnectionNotice>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The service shared an email that has an account: the note says which, and "Not now" names the service. */
export const KnownEmail: Story = {
    play: async ({ canvas }) => {
        await expect(canvas.getByRole('status')).toHaveTextContent(
            'You already have a Traceway account with s•••@example.com. Log in to it to connect Wahoo.',
        );
        await expect(
            canvas.getByRole('button', {
                name: /^Not now\s*,\s*do not connect Wahoo$/,
            }),
        ).toBeEnabled();
    },
};

/** No email from the service: the rider confirms once they are in. */
export const ConfirmAfterLogIn: Story = {
    args: {
        pending: {
            slug: 'ride_with_gps',
            label: 'Ride with GPS',
            email: null,
            confirm: true,
        },
    },
    play: async ({ canvas }) => {
        await expect(canvas.getByRole('status')).toHaveTextContent(
            'Log in to your Traceway account to connect Ride with GPS. You confirm it once you are in.',
        );
    },
};

/** With `focus`, the note takes focus instead of being a live note, so a screen reader reads it once. */
export const Focused: Story = {
    args: { focus: true },
    play: async ({ canvas, canvasElement }) => {
        await waitFor(() =>
            expect(canvasElement.querySelector('.notice')).toHaveFocus(),
        );
        await expect(canvas.queryByRole('status')).not.toBeInTheDocument();
    },
};
