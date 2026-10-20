import type { Meta, StoryObj } from '@storybook/react-vite';
import '../../../css/login-scene.css';
import { expect } from 'storybook/test';
import SignInLegal from '@/components/sign-in-legal';

/**
 * The terms and the privacy policy a new rider accepts, under the services or above a form that opens an account.
 * They are pages of the content site, so they are plain links.
 */
const meta = {
    title: 'Components/SignInLegal',
    component: SignInLegal,
    args: { intent: 'login' },
    argTypes: {
        intent: {
            control: 'inline-radio',
            options: ['login', 'register', 'create'],
            description:
                'Under the services on Log in or Sign up, or above a form that creates the account.',
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
} satisfies Meta<typeof SignInLegal>;

export default meta;

type Story = StoryObj<typeof meta>;

const linksToTheSite = async ({
    canvas,
}: {
    canvas: Parameters<NonNullable<Story['play']>>[0]['canvas'];
}) => {
    await expect(
        canvas.getByRole('link', { name: 'terms of use' }),
    ).toHaveAttribute('href', '/terms');
    await expect(
        canvas.getByRole('link', { name: 'privacy policy' }),
    ).toHaveAttribute('href', '/privacy');
};

export const LogIn: Story = {
    play: async (context) => {
        await expect(
            context.canvas.getByText(/New to Traceway\?/),
        ).toBeVisible();
        await linksToTheSite(context);
    },
};

export const SignUp: Story = {
    args: { intent: 'register' },
    play: async (context) => {
        await expect(
            context.canvas.getByText(/^Continuing opens your account/),
        ).toBeVisible();
        await linksToTheSite(context);
    },
};

export const CreateAccount: Story = {
    args: { intent: 'create' },
    play: async (context) => {
        await expect(
            context.canvas.getByText(/^By creating an account/),
        ).toBeVisible();
        await linksToTheSite(context);
    },
};
