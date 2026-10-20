import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect } from 'storybook/test';
import {
    Loading,
    NotConnected,
    Unavailable,
} from '@/components/admin/traffic-states';
import { useAppearance } from '@/hooks/use-appearance';

/** The admin area around the state, in Paper or Ink with the toolbar. */
function AdminScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div style={{ maxWidth: 720 }}>{children}</div>
        </div>
    );
}

/**
 * What a Traffic page shows while Cloudflare is asked, when it is not set up, and when it did not answer. Never
 * zeros that look like all clear.
 */
const meta = {
    title: 'Components/Admin/TrafficStates',
    component: Unavailable,
    args: {
        error: 'Cloudflare did not answer in time. Try again in a minute.',
    },
    argTypes: {
        error: {
            control: 'text',
            description:
                "Cloudflare's reason in plain words, shown after Not available right now.",
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <AdminScene>
                <Story />
            </AdminScene>
        ),
    ],
} satisfies Meta<typeof Unavailable>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Busy while Cloudflare is asked, so a screen reader waits instead of reading an empty panel. */
export const AskingCloudflare: Story = {
    render: () => <Loading />,
    play: async ({ canvas }) => {
        const heading = canvas.getByRole('heading', {
            name: 'Asking Cloudflare',
        });
        await expect(heading.closest('section')).toHaveAttribute(
            'aria-busy',
            'true',
        );
    },
};

/** Names every setting the server still needs, so the admin knows what to set. */
export const NotSetUp: Story = {
    render: () => <NotConnected />,
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('heading', { name: 'Not connected' }),
        ).toBeVisible();

        for (const setting of [
            'CLOUDFLARE_ANALYTICS_TOKEN',
            'CLOUDFLARE_ZONE_ID',
            'CLOUDFLARE_ACCOUNT_ID',
            'CLOUDFLARE_WEB_ANALYTICS_SITE_TAG',
        ]) {
            await expect(canvas.getByText(setting)).toBeVisible();
        }
    },
};

/** Said in a status with Cloudflare's reason, and no numbers at all. */
export const NotAvailable: Story = {
    play: async ({ args, canvas }) => {
        const status = canvas.getByRole('status');
        await expect(status).toHaveTextContent('Not available right now.');
        await expect(status).toHaveTextContent(args.error);
        await expect(canvas.queryByText('0')).toBeNull();
    },
};
