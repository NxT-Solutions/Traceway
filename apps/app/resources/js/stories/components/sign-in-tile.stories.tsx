import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import '../../../css/login-scene.css';
import { expect, waitFor } from 'storybook/test';
import SignInTile from '@/components/sign-in-tile';
import { signIn } from '@/routes/connections';

/**
 * One service on the log in pages. Google always reads "Continue with Google" on screen; the others show their
 * name alone in the grid and say "Continue with" to screen readers, unless `spelled`. "Last used" marks how this
 * browser logged in before. A click turns the mark into a spinner while the service's page loads.
 */
const meta = {
    title: 'Components/SignInTile',
    component: SignInTile,
    args: {
        option: { slug: 'google', label: 'Google' },
        lastUsed: false,
    },
    argTypes: {
        option: {
            control: 'object',
            description:
                'The service: its `slug` and the `label` riders know it by.',
        },
        lastUsed: {
            control: 'boolean',
            description: 'This browser logged in with it last time.',
        },
        remember: {
            control: 'boolean',
            description:
                '"Remember me" is ticked, so the rider stays logged in after the service sends them back.',
        },
        spelled: {
            control: 'boolean',
            description:
                'Show "Continue with" on screen, not only to a screen reader.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <div className="tw-login" style={{ minHeight: 0 }}>
                <div className="form" style={{ padding: 24 }}>
                    <ul className="providers single">
                        <li>
                            <Story />
                        </li>
                    </ul>
                    <button
                        type="button"
                        className="text-btn"
                        style={{ marginTop: 16 }}
                        onClick={linkTo('Components/SignInOptions', 'Log In')}
                    >
                        Every featured service: SignInOptions
                    </button>
                </div>
            </div>
        ),
    ],
} satisfies Meta<typeof SignInTile>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A link that says "Continue with Google" on screen and to a screen reader, and goes to Google's log in. */
export const Google: Story = {
    play: async ({ canvas, args }) => {
        const link = canvas.getByRole('link', {
            name: `Continue with ${args.option.label}`,
        });

        await expect(link).toHaveAttribute(
            'href',
            signIn.url(args.option.slug),
        );
        await expect(canvas.getByText(/Continue with/)).toBeVisible();
    },
};

/** "Last used" is part of the link's name, after a comma. */
export const LastUsed: Story = {
    args: { option: { slug: 'wahoo', label: 'Wahoo' }, lastUsed: true },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('link', {
                name: /^Continue with Wahoo\s*,\s*Last used$/,
            }),
        ).toBeVisible();
    },
};

/** In the grid of every service, the name alone shows on screen; a screen reader still hears "Continue with". */
export const NameOnly: Story = {
    args: { option: { slug: 'openstreetmap', label: 'OpenStreetMap' } },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('link', {
                name: 'Continue with OpenStreetMap',
            }),
        ).toBeVisible();
        await expect(canvas.getByText('Continue with')).toHaveClass('vh');
    },
};

export const Spelled: Story = {
    args: {
        option: { slug: 'ride_with_gps', label: 'Ride with GPS' },
        spelled: true,
    },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('link', {
                name: 'Continue with Ride with GPS',
            }),
        ).toBeVisible();
    },
};

/** "Remember me" goes along to the service and back. */
export const Remembered: Story = {
    args: { remember: true },
    play: async ({ canvas, args }) => {
        await expect(canvas.getByRole('link')).toHaveAttribute(
            'href',
            signIn.url(args.option.slug, { query: { remember: 1 } }),
        );
    },
};

/** After a click, the link is busy and its mark turns into a spinner while the service's page loads. */
export const Opening: Story = {
    play: async ({ canvas, userEvent }) => {
        // The story stays put: the service's page is not opened.
        const stay = (event: MouseEvent) => event.preventDefault();

        window.addEventListener('click', stay);

        try {
            const link = canvas.getByRole('link', {
                name: 'Continue with Google',
            });

            await userEvent.click(link);
            await waitFor(() =>
                expect(link).toHaveAttribute('aria-busy', 'true'),
            );
        } finally {
            window.removeEventListener('click', stay);
        }
    },
};
