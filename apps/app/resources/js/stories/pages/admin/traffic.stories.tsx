import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, waitFor } from 'storybook/test';
import Traffic from '@/pages/admin/traffic';
import TrafficBackups from '@/pages/admin/traffic/backups';
import TrafficBots from '@/pages/admin/traffic/bots';
import TrafficRequests from '@/pages/admin/traffic/requests';
import TrafficSecurity from '@/pages/admin/traffic/security';
import TrafficSpeed from '@/pages/admin/traffic/speed';
import { traffic as trafficRoute } from '@/routes/admin';
import {
    backups as backupsRoute,
    bots as botsRoute,
    requests as requestsRoute,
    security as securityRoute,
    speed as speedRoute,
} from '@/routes/admin/traffic';
import {
    backups,
    bots,
    requests,
    security,
    speed,
    traffic,
} from '../../support/admin-pages';
import { adminAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { delay, http, inertiaPage } from '../../support/server';

type TrafficProps = ComponentProps<typeof Traffic>;

/**
 * The Traffic tab at `/admin/traffic`. AdminTrafficController defers the report: the page opens while Cloudflare is
 * asked, then the report arrives. Here it arrives with the page, as example numbers.
 *
 * `SwitchesTabs` runs against a mocked server (Mock Service Worker) that answers each tab as
 * AdminTrafficController and AdminTrafficInsightsController do: the page first, without its report and naming it
 * as deferred, then the report alone when Inertia asks for it. Each tab shows "Asking Cloudflare" in between.
 */
const meta = {
    title: 'Pages/Admin/Traffic/Overview',
    component: Traffic,
    parameters: { layout: 'fullscreen' },
    args: { traffic },
    render: (args) => (
        <InertiaPage<TrafficProps>
            name="admin/traffic"
            component={Traffic}
            url={trafficRoute.url()}
            props={args}
            shared={sharedProps(adminAuth)}
            pages={{
                'admin/traffic/requests': TrafficRequests,
                'admin/traffic/security': TrafficSecurity,
                'admin/traffic/bots': TrafficBots,
                'admin/traffic/speed': TrafficSpeed,
                'admin/traffic/backups': TrafficBackups,
            }}
        />
    ),
} satisfies Meta<typeof Traffic>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Visits, requests, the most read pages, and where visitors came from, last 30 days. */
export const Default: Story = {};

/** While Cloudflare is asked: the deferred report has not arrived. */
export const Loading: Story = {
    args: { traffic: undefined },
};

/** When the Cloudflare token is not set on the server. */
export const NotConnected: Story = {
    args: { traffic: { ...traffic, connected: false } },
};

/** Each tab's address, its page, and its deferred report, as the controllers render them. */
const tabs = [
    [trafficRoute.url(), 'admin/traffic', 'traffic', traffic],
    [requestsRoute.url(), 'admin/traffic/requests', 'insights', requests],
    [securityRoute.url(), 'admin/traffic/security', 'insights', security],
    [botsRoute.url(), 'admin/traffic/bots', 'insights', bots],
    [speedRoute.url(), 'admin/traffic/speed', 'insights', speed],
    [backupsRoute.url(), 'admin/traffic/backups', 'insights', backups],
] as const;

/** The reports Inertia fetched, by page. */
const reportRequest = fn();

/**
 * The admin opens Requests, then Security, then goes back to Visitors. Each tab opens at once with "Asking
 * Cloudflare", then its report fills in.
 */
export const SwitchesTabs: Story = {
    beforeEach({ msw }) {
        reportRequest.mockClear();
        msw.use(
            ...tabs.map(([url, component, report, data]) =>
                http.get(url, async ({ request }) => {
                    if (request.headers.has('X-Inertia-Partial-Data')) {
                        reportRequest(component);
                        // Cloudflare takes a moment.
                        await delay(600);
                    }

                    return inertiaPage(
                        component,
                        { [report]: data },
                        {
                            url,
                            shared: sharedProps(adminAuth),
                            request,
                            deferred: [report],
                        },
                    );
                }),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        const tab = (name: string) => canvas.getByRole('link', { name });

        await step('Open Requests', async () => {
            await userEvent.click(tab('Requests'));
        });

        await step('Requests asks Cloudflare, then fills in', async () => {
            await expect(
                await canvas.findByRole('heading', {
                    name: 'Asking Cloudflare',
                }),
            ).toBeVisible();
            await expect(
                await canvas.findByRole('heading', { name: 'Last 72 hours' }),
            ).toBeVisible();
            await expect(tab('Requests')).toHaveAttribute(
                'aria-current',
                'page',
            );
        });

        await step('Open Security', async () => {
            await userEvent.click(tab('Security'));
            await expect(
                await canvas.findByRole('heading', { name: 'Firewall blocks' }),
            ).toBeVisible();
            await expect(tab('Security')).toHaveAttribute(
                'aria-current',
                'page',
            );
        });

        await step('Back to Visitors', async () => {
            await userEvent.click(tab('Visitors'));
            await waitFor(() =>
                expect(tab('Visitors')).toHaveAttribute('aria-current', 'page'),
            );
            await expect(
                await canvas.findByRole('heading', { name: 'Last 30 days' }),
            ).toBeVisible();
            await expect(reportRequest.mock.calls).toEqual([
                ['admin/traffic/requests'],
                ['admin/traffic/security'],
                ['admin/traffic'],
            ]);
        });
    },
};
