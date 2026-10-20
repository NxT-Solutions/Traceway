import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import '../../../css/login-scene.css';
import { expect } from 'storybook/test';
import SignInOptionsGrid from '@/components/sign-in-options';

/**
 * The services riders log in with most, the one this browser used last first, then a way to the rest and the
 * terms. Nothing at all when this Traceway has no service set up, so the page is the email form alone.
 */
const meta = {
    title: 'Components/SignInOptions',
    component: SignInOptionsGrid,
    args: {
        options: {
            featured: [
                { slug: 'google', label: 'Google' },
                { slug: 'wahoo', label: 'Wahoo' },
                { slug: 'ride_with_gps', label: 'Ride with GPS' },
                { slug: 'github', label: 'GitHub' },
            ],
            more: 3,
            lastUsed: 'wahoo',
        },
    },
    argTypes: {
        options: {
            control: 'object',
            description:
                'The featured services in order, how many more there are, and the one this browser used last.',
        },
        remember: {
            control: 'boolean',
            description: '"Remember me" on the log in form is ticked.',
        },
        intent: {
            control: 'inline-radio',
            options: ['login', 'register'],
            description: 'On Log in or on Sign up: the words follow it.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <div className="tw-login" style={{ minHeight: 0 }}>
                <div className="form" style={{ padding: 24 }}>
                    <Story />
                    <button
                        type="button"
                        className="text-btn"
                        style={{ marginTop: 16 }}
                        onClick={linkTo('Components/SignInTile', 'Last Used')}
                    >
                        One service: SignInTile
                    </button>
                </div>
            </div>
        ),
    ],
} satisfies Meta<typeof SignInOptionsGrid>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Each featured service says "Continue with" it, the last used one is marked, then the rest and the terms. */
export const LogIn: Story = {
    play: async ({ canvas, args }) => {
        const services = canvas.getAllByRole('link', {
            name: /^Continue with/,
        });

        await expect(services).toHaveLength(args.options.featured.length);
        await expect(services[1]).toHaveAccessibleName(
            /^Continue with Wahoo\s*,\s*Last used$/,
        );
        await expect(
            canvas.getByRole('link', {
                name: /more ways to log in\s*,\s*3 more/i,
            }),
        ).toBeVisible();
        await expect(
            canvas.getByRole('link', { name: 'terms of use' }),
        ).toHaveAttribute('href', '/terms');
    },
};

/** On Sign up the way to the rest says so, and keeps the rider signing up. */
export const SignUp: Story = {
    args: { intent: 'register' },
    play: async ({ canvas }) => {
        const more = canvas.getByRole('link', {
            name: /more ways to sign up/i,
        });

        await expect(more.getAttribute('href')).toContain('intent=register');
    },
};

/** "Remember me" goes along to the rest of the services. */
export const Remembered: Story = {
    args: { remember: true },
    play: async ({ canvas }) => {
        const more = canvas.getByRole('link', { name: /more ways to log in/i });

        await expect(more.getAttribute('href')).toContain('remember=1');
    },
};

/** Two services, and nothing more behind "More ways to log in". */
export const FewServices: Story = {
    args: {
        options: {
            featured: [
                { slug: 'google', label: 'Google' },
                { slug: 'openstreetmap', label: 'OpenStreetMap' },
            ],
            more: 0,
            lastUsed: null,
        },
    },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('link', { name: 'Continue with OpenStreetMap' }),
        ).toBeVisible();
        await expect(
            canvas.queryByRole('link', { name: /more ways/i }),
        ).not.toBeInTheDocument();
    },
};

/** No service set up: nothing renders. */
export const NoServices: Story = {
    args: { options: { featured: [], more: 0, lastUsed: null } },
    play: async ({ canvas }) => {
        await expect(
            canvas.queryByRole('link', { name: /continue with/i }),
        ).not.toBeInTheDocument();
    },
};
