import type { Meta, StoryObj } from '@storybook/react-vite';
import '../../../css/login-scene.css';
import { expect, waitFor } from 'storybook/test';
import SignInAlert from '@/components/sign-in-alert';

/**
 * Why a service did not log the rider in, and what to do next. With `focus`, it takes focus once the page is ready
 * so a screen reader reads it before the form. Nothing at all without a message.
 */
const meta = {
    title: 'Components/SignInAlert',
    component: SignInAlert,
    args: {
        message:
            'Wahoo did not let Traceway in. Try again, or log in with your email.',
    },
    argTypes: {
        message: {
            control: 'text',
            description:
                'What went wrong and what to do next. Leave it empty and nothing renders.',
        },
        focus: {
            control: 'boolean',
            description:
                'Take focus once the page is ready, instead of being an alert, so it is read once, before the form.',
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
} satisfies Meta<typeof SignInAlert>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Announced as an alert. */
export const Default: Story = {
    play: async ({ canvas, args }) => {
        await expect(canvas.getByRole('alert')).toHaveTextContent(
            args.message ?? '',
        );
    },
};

/** Takes focus, and is not also an alert, so a screen reader reads it once. */
export const Focused: Story = {
    args: { focus: true },
    play: async ({ canvas, args }) => {
        const note = canvas.getByText(args.message ?? '').parentElement;

        await waitFor(() => expect(note).toHaveFocus());
        await expect(canvas.queryByRole('alert')).not.toBeInTheDocument();
    },
};

/** No message: nothing renders. */
export const Empty: Story = {
    args: { message: undefined },
    play: async ({ canvasElement }) => {
        await expect(canvasElement.querySelector('.notice')).toBeNull();
    },
};
