import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, userEvent, within } from 'storybook/test';
import Share from '@/pages/share';
import { guestAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { catchCopies } from '../../support/planner-downloads';
import { sharedDuathlon, sharedRoute } from '../../support/planner-pages';
import { plannerHandlers } from '../../support/planner-server';

type ShareProps = ComponentProps<typeof Share>;

/**
 * The page a share link opens, `/share/{route}`, with the props ShowSharedRouteController renders: the route, its
 * details, and its files, for anyone with the link, signed in or not. The stories open it as a guest. `draw_image`
 * is false: when it is true, the owner's browser draws the link's picture and sends it to the server. Mock Service
 * Worker answers the GPX and FIT links (ExportSharedCourseController) with small course files. The map tiles come
 * from OpenFreeMap, so the map needs a connection.
 */
const meta = {
    title: 'Pages/Planner/Share',
    component: Share,
    parameters: { layout: 'fullscreen' },
    args: { route: sharedRoute, draw_image: false },
    render: (args) => (
        <InertiaPage<ShareProps>
            name="share"
            component={Share}
            url="/share/2b9e4c71-8d3f-4a62-b1e0-5c7d9a3f6e18?signature=example"
            props={args}
            shared={sharedProps(guestAuth)}
        />
    ),
    beforeEach: ({ msw }) => {
        msw.use(...plannerHandlers);
    },
} satisfies Meta<typeof Share>;

export default meta;

type Story = StoryObj<typeof meta>;

/** What a course link answers: its file name and its first bytes. */
async function download(
    href: string | null,
): Promise<{ type: string | null; filename: string; bytes: Uint8Array }> {
    const response = await fetch(href ?? '');

    return {
        type: response.headers.get('Content-Type'),
        filename:
            response.headers
                .get('Content-Disposition')
                ?.match(/filename="([^"]+)"/)?.[1] ?? '',
        bytes: new Uint8Array(await response.arrayBuffer()),
    };
}

/** What the page copied, caught by the story that runs now. */
let copies: ReturnType<typeof catchCopies> = { copied: [], restore: () => {} };

/**
 * A 9 km run around Westerlo: the map, what it was planned with, the climb, surfaces, turns, and stops. The GPX and
 * FIT links give the course files, and Copy link copies the page's address.
 */
export const Route: Story = {
    beforeEach: () => {
        copies = catchCopies();

        return copies.restore;
    },
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);

        await step('GPX gives the course as a GPX track', async () => {
            const gpx = await canvas.findByRole('link', { name: 'GPX' });
            await expect(gpx).toHaveAttribute('href', sharedRoute.gpx_url);
            const file = await download(gpx.getAttribute('href'));
            await expect(file.type).toBe('application/gpx+xml');
            await expect(file.filename).toBe('westerlo-morning-run.gpx');
            await expect(new TextDecoder().decode(file.bytes)).toContain(
                '<name>Westerlo morning run</name>',
            );
        });

        await step('FIT gives the course for a Garmin', async () => {
            const fit = canvas.getByRole('link', { name: 'FIT' });
            await expect(fit).toHaveAttribute('href', sharedRoute.fit_url);
            const file = await download(fit.getAttribute('href'));
            await expect(file.filename).toBe('westerlo-morning-run.fit');
            // A FIT file says so in bytes 8 to 11.
            await expect(String.fromCharCode(...file.bytes.slice(8, 12))).toBe(
                '.FIT',
            );
        });

        await step('Copy link copies this page', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Copy link' }),
            );
            await expect(
                await canvas.findByRole('button', { name: 'Copied' }),
            ).toBeVisible();
            await expect(copies.copied).toEqual([window.location.href]);
        });
    },
};

/**
 * A duathlon: each leg in its own colour, and the climb of one land leg at a time. Each land leg has its own FIT
 * course, named for the device.
 */
export const Multisport: Story = {
    args: { route: sharedDuathlon },
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);

        await step('One FIT course per leg', async () => {
            const legs = await canvas.findAllByRole('link', {
                name: /^FIT \d/,
            });
            await expect(legs.map((link) => link.textContent)).toEqual([
                'FIT 1 Run',
                'FIT 2 Road',
                'FIT 3 Run',
            ]);
            const file = await download(legs[1]?.getAttribute('href') ?? null);
            await expect(file.filename).toMatch(/-2-.+\.fit$/);
            await expect(String.fromCharCode(...file.bytes.slice(8, 12))).toBe(
                '.FIT',
            );
        });

        await step('Show the ride’s climb', async () => {
            const ride = canvas.getByRole('tab', { name: '2 Road' });
            await userEvent.click(ride);
            await expect(ride).toHaveAttribute('aria-selected', 'true');
        });
    },
};

/** On a phone the card sits under the map. */
export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};

/**
 * Only the map, as ShowSharedRouteEmbedController renders it (`embed: true`) for the card a chat app shows after
 * an assistant planned a route.
 */
export const Embed: Story = {
    args: { route: sharedRoute, embed: true, draw_image: undefined },
};
