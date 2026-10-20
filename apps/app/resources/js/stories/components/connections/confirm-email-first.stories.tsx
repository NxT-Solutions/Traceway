import '../../../../css/settings-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect } from 'storybook/test';
import ConfirmEmailFirst from '@/components/connections/confirm-email-first';
import { useAppearance } from '@/hooks/use-appearance';

/** The Connections page around the note, in Paper or Ink with the toolbar. */
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
 * Adding or removing a connection waits until the rider confirms their email. This says so once, with a way to
 * get the email again, instead of buttons that end on the confirm page.
 */
const meta = {
    title: 'Components/Connections/ConfirmEmailFirst',
    component: ConfirmEmailFirst,
    args: { id: 'confirm-email-first', sent: false },
    argTypes: {
        id: {
            control: 'text',
            description:
                'The id of the sentence Connect and Disconnect point to for why they are off.',
        },
        sent: {
            control: 'boolean',
            description:
                'True right after the rider asked for the email again.',
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
} satisfies Meta<typeof ConfirmEmailFirst>;

export default meta;

type Story = StoryObj<typeof meta>;

/** One button, reached with the keyboard, and an empty status beside it that will say when the email is sent. */
export const Default: Story = {
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        await expect(
            canvasElement.querySelector(`#${args.id}`),
        ).toHaveTextContent('Confirm your email first.');

        await userEvent.tab();
        await expect(
            canvas.getByRole('button', { name: 'Send the email again' }),
        ).toHaveFocus();
        await expect(canvas.getByRole('status')).toBeEmptyDOMElement();
    },
};

/** Right after the rider asked for the email again: the status says so, and the button stays. */
export const Sent: Story = {
    args: { sent: true },
    play: async ({ canvas }) => {
        await expect(canvas.getByRole('status')).toHaveTextContent(
            'Sent. Open the link in the email.',
        );
        await expect(
            canvas.getByRole('button', { name: 'Send the email again' }),
        ).toBeEnabled();
    },
};
