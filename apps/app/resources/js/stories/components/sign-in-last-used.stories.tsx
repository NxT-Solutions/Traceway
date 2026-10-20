import type { Meta, StoryObj } from '@storybook/react-vite';
import '../../../css/login-scene.css';
import { expect } from 'storybook/test';
import LastUsed from '@/components/sign-in-last-used';

/**
 * How the rider last logged in on this browser, as a small pill. Inside a button, a comma before it keeps the
 * button's name readable; beside a field, give it an id and point the field at it with `aria-describedby`.
 */
const meta = {
    title: 'Components/SignInLastUsed',
    component: LastUsed,
    argTypes: {
        id: {
            control: 'text',
            description:
                'An id, so a field beside it can point at it with `aria-describedby`.',
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
} satisfies Meta<typeof LastUsed>;

export default meta;

type Story = StoryObj<typeof meta>;

/** "Last used" on screen; the comma before it is only for screen readers. */
export const Default: Story = {
    play: async ({ canvas }) => {
        await expect(canvas.getByText('Last used')).toBeVisible();
        await expect(canvas.getByText(',')).toHaveClass('vh');
    },
};

/** Beside the password field, the way it marks the email form: the field is described by it. */
export const BesideAField: Story = {
    args: { id: 'password-last-used' },
    render: (args) => (
        <div className="field">
            <div className="lab" style={{ display: 'flex', gap: 8 }}>
                <label htmlFor="password">Password</label>
                <LastUsed {...args} />
            </div>
            <input
                id="password"
                type="password"
                aria-describedby={args.id}
                style={{ width: '100%', height: 44 }}
            />
        </div>
    ),
    play: async ({ canvas, userEvent }) => {
        const field = canvas.getByLabelText('Password');

        await expect(field).toHaveAccessibleDescription(/Last used/);
        await userEvent.click(field);
        await userEvent.keyboard('westerlo-loop-41');
        await expect(field).toHaveValue('westerlo-loop-41');
    },
};
