import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import TrafficTabs from '@/components/admin/traffic-tabs';
import { useAppearance } from '@/hooks/use-appearance';
import { adminAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { followLinks } from '../../support/story-links';

/** The admin area around the tabs, in Paper or Ink with the toolbar. */
function AdminScene() {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <TrafficTabs />
        </div>
    );
}

/** The tabs mark the page they are on, so they render inside Inertia's page at that address. */
function Tabs({ url }: { url: string }) {
    return (
        <InertiaPage
            name="dashboard"
            component={AdminScene}
            props={{}}
            shared={sharedProps(adminAuth)}
            url={url}
        />
    );
}

const pages = [
    'Visitors',
    'Requests',
    'Security',
    'AI and bots',
    'Speed',
    'Backups',
];

/**
 * The Traffic pages, one per question Cloudflare can answer. The tab of the page the admin is on is marked as the
 * current page. A tab opens that page's story.
 */
const meta = {
    title: 'Components/Admin/TrafficTabs',
    component: Tabs,
    args: { url: '/admin/traffic' },
    argTypes: {
        url: {
            control: 'select',
            options: [
                '/admin/traffic',
                '/admin/traffic/requests',
                '/admin/traffic/security',
                '/admin/traffic/bots',
                '/admin/traffic/speed',
                '/admin/traffic/backups',
            ],
            description:
                'The address the admin is on. Its tab is marked as the current page.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        followLinks({
            '/admin/traffic': ['Pages/Admin/Traffic/Overview', 'Default'],
            '/admin/traffic/requests': [
                'Pages/Admin/Traffic/Requests',
                'Default',
            ],
            '/admin/traffic/security': [
                'Pages/Admin/Traffic/Security',
                'Default',
            ],
            '/admin/traffic/bots': ['Pages/Admin/Traffic/Bots', 'Default'],
            '/admin/traffic/speed': ['Pages/Admin/Traffic/Speed', 'Default'],
            '/admin/traffic/backups': [
                'Pages/Admin/Traffic/Backups',
                'Default',
            ],
        }),
    ],
} satisfies Meta<typeof Tabs>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Six links in a named navigation, in order, and only the page the admin is on is current. */
export const Visitors: Story = {
    play: async ({ canvas, userEvent }) => {
        const nav = canvas.getByRole('navigation', { name: 'Traffic pages' });
        const links = await canvas.findAllByRole('link');

        await expect(nav).toBeVisible();
        await expect(links.map((link) => link.textContent)).toEqual(pages);
        await expect(
            canvas.getByRole('link', { name: 'Visitors' }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(
            canvas.getByRole('link', { name: 'Speed' }),
        ).not.toHaveAttribute('aria-current');

        // The keyboard reaches every tab in the order they are drawn.
        for (const page of pages) {
            await userEvent.tab();
            await expect(
                canvas.getByRole('link', { name: page }),
            ).toHaveFocus();
        }
    },
};

export const Speed: Story = {
    args: { url: '/admin/traffic/speed' },
    play: async ({ canvas }) => {
        await expect(
            await canvas.findByRole('link', { name: 'Speed' }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(
            canvas.getByRole('link', { name: 'Visitors' }),
        ).not.toHaveAttribute('aria-current');
        await expect(
            canvas.getByRole('link', { name: 'Backups' }),
        ).toHaveAttribute('href', '/admin/traffic/backups');
    },
};
