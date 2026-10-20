import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import RankedList from '@/components/admin/ranked-list';
import StatTile from '@/components/admin/stat-tile';
import TrafficPage from '@/components/admin/traffic-page';
import { useAppearance } from '@/hooks/use-appearance';
import { adminAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';

type Insights = {
    connected: boolean;
    error: string | null;
    visits: number;
    countries: { label: string; count: number }[];
};

/** One Traffic page in the admin area, in Paper or Ink with the toolbar. */
function VisitorsPage({ insights }: { insights?: Insights }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <div
                className="section"
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    maxWidth: 720,
                }}
            >
                <TrafficPage title="Visitors" insights={insights}>
                    {(answer) => (
                        <section className="panel">
                            <div className="tiles">
                                <StatTile
                                    label="Visits this week"
                                    value={answer.visits}
                                />
                            </div>
                            <RankedList
                                items={answer.countries}
                                empty="No visits yet."
                            />
                        </section>
                    )}
                </TrafficPage>
            </div>
        </div>
    );
}

/** The page waits on Cloudflare as a deferred prop, so it renders inside Inertia's page. */
function Page({ insights }: { insights?: Insights }) {
    return (
        <InertiaPage
            name="dashboard"
            component={VisitorsPage}
            props={insights === undefined ? {} : { insights }}
            shared={sharedProps(adminAuth)}
            url="/admin/traffic"
        />
    );
}

/**
 * One Traffic page: the tabs, then its part once Cloudflare answered. Each part fails on its own, with a note in
 * place of its numbers, never zeros that look like all clear.
 */
const meta = {
    title: 'Components/Admin/TrafficPage',
    component: Page,
    args: {
        insights: {
            connected: true,
            error: null,
            visits: 3_412,
            countries: [
                { label: 'Belgium', count: 1_820 },
                { label: 'Netherlands', count: 744 },
                { label: 'Germany', count: 391 },
                { label: 'France', count: 268 },
            ],
        },
    },
    argTypes: {
        insights: {
            control: 'object',
            description:
                "Cloudflare's answer, a deferred prop: left out while it is asked, `connected: false` when it is not set up, or an `error` when it did not answer.",
        },
    },
    parameters: { layout: 'fullscreen' },
} satisfies Meta<typeof Page>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The tabs, with Visitors current, then the numbers. */
export const Answered: Story = {
    play: async ({ canvas }) => {
        await expect(
            await canvas.findByRole('link', { name: 'Visitors' }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(canvas.getByText('Visits this week')).toBeVisible();
        await expect(canvas.getByText('3,412')).toBeVisible();

        const countries = within(canvas.getByRole('list')).getAllByRole(
            'listitem',
        );
        await expect(countries[0]).toHaveTextContent('Belgium1,820');
    },
};

/** Still asking: a busy panel, no numbers. */
export const Waiting: Story = {
    args: { insights: undefined },
    play: async ({ canvas }) => {
        await expect(
            await canvas.findByRole('heading', { name: 'Asking Cloudflare' }),
        ).toBeVisible();
        await expect(canvas.queryByText('Visits this week')).toBeNull();
    },
};

export const NotConnected: Story = {
    args: {
        insights: { connected: false, error: null, visits: 0, countries: [] },
    },
    play: async ({ canvas }) => {
        await expect(
            await canvas.findByRole('heading', { name: 'Not connected' }),
        ).toBeVisible();
        // Never zeros that look like all clear.
        await expect(canvas.queryByText('Visits this week')).toBeNull();
    },
};

export const CloudflareDown: Story = {
    args: {
        insights: {
            connected: true,
            error: 'Cloudflare did not answer in time. Try again in a minute.',
            visits: 0,
            countries: [],
        },
    },
    play: async ({ canvas }) => {
        await expect(await canvas.findByRole('status')).toHaveTextContent(
            'Not available right now. Cloudflare did not answer in time.',
        );
        await expect(canvas.queryByText('Visits this week')).toBeNull();
    },
};
