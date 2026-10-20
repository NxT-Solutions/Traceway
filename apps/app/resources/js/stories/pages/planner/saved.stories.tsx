import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, screen, userEvent, waitFor, within } from 'storybook/test';
import Saved from '@/pages/saved';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { catchCopies } from '../../support/planner-downloads';
import { libraryRoutes, routeServices } from '../../support/planner-pages';
import {
    type PlannerServer,
    plannerServer,
} from '../../support/planner-server';

type SavedProps = ComponentProps<typeof Saved>;
type Layout = 'grid' | 'map' | 'list';
type LibraryArgs = SavedProps & {
    /** The layout the library remembers in this browser. */
    layout: Layout;
};

const layoutKey = 'traceway.library.layout';
const brandKey = 'traceway.send-to-device.brand';

function remember(layout: Layout | null): void {
    try {
        if (layout === null) {
            window.localStorage.removeItem(layoutKey);
            window.localStorage.removeItem(brandKey);
        } else {
            window.localStorage.setItem(layoutKey, layout);
        }
    } catch {
        // Storage can be blocked. The library then opens as a grid.
    }
}

/** The library on the layout the Controls pick. The page reads it once, so a new layout remounts the page. */
function RouteLibrary({ layout, ...props }: LibraryArgs) {
    remember(layout);

    return (
        <InertiaPage<SavedProps>
            key={layout}
            name="saved"
            component={Saved}
            url="/saved"
            props={props}
            shared={{ ...sharedProps(), routeServices }}
        />
    );
}

/** The mock server of the story that runs now, so its play function can check what the library sent. */
let server: PlannerServer = plannerServer();

/**
 * The route library at `/saved`, with the props SavedRouteLibraryController renders: every route the rider saved,
 * newest first, its line thinned for the thumbnails and the overview map. Mock Service Worker plays the server
 * (`stories/support/planner-server.ts`): a route's climb (`/routes/saved/{id}/details`), Delete, and Send to device
 * all get answers. The thumbnails are drawn on an OpenFreeMap map, so they need a connection; without one each card
 * keeps the outline of its route.
 */
const meta = {
    title: 'Pages/Planner/Saved',
    component: RouteLibrary,
    parameters: { layout: 'fullscreen' },
    args: { routes: libraryRoutes, layout: 'grid' },
    argTypes: {
        layout: { control: 'inline-radio', options: ['grid', 'map', 'list'] },
    },
    beforeEach: ({ msw }) => {
        server = plannerServer();
        msw.use(...server.handlers);
        remember(null);

        return () => remember(null);
    },
} satisfies Meta<typeof RouteLibrary>;

export default meta;

type Story = StoryObj<typeof meta>;

/** How long to wait for the mock server, while the map loads its tiles over the same connection. */
const answer = { timeout: 5000 };

/** The names of the routes the library shows now, as cards or rows. */
function shown(canvasElement: HTMLElement): string[] {
    return [
        ...canvasElement.querySelectorAll(
            '.grid .card .title, .list .sheet-card .title',
        ),
    ].map((title) => title.textContent ?? '');
}

/** Opens a route from the grid by its name. */
async function open(canvasElement: HTMLElement, name: string): Promise<void> {
    const canvas = within(canvasElement);
    await userEvent.click(
        await canvas.findByRole(
            'button',
            { name: new RegExp(name) },
            { timeout: 10_000 },
        ),
    );
    await canvas.findByRole('heading', { name, level: 2 });
}

/** Six routes as cards: a run, gravel, a duathlon, road, a trail run, and a walk. */
export const Library: Story = {};

/** Every route on one map, with the list beside it. */
export const MapLayout: Story = {
    args: { layout: 'map' },
};

/** One row per route, for a long library. */
export const ListLayout: Story = {
    args: { layout: 'list' },
};

/** A rider who has not saved a route yet. */
export const Empty: Story = {
    args: { routes: [] },
};

/** What the page copied, caught by the story that runs now. */
let copies: ReturnType<typeof catchCopies> = { copied: [], restore: () => {} };

/** Opening a route shows its figures, then the climb and surfaces the server kept with it. Copy link copies its share link. */
export const OpenARoute: Story = {
    beforeEach: () => {
        copies = catchCopies();

        return copies.restore;
    },
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);
        const [route] = libraryRoutes;

        await step('Open the morning run', async () => {
            await open(canvasElement, 'Westerlo morning run');
            await expect(canvas.getByText('Distance')).toBeVisible();
        });

        await step('Its climb and surfaces arrive', async () => {
            await expect(
                await canvas.findByRole(
                    'slider',
                    { name: 'Height along the route' },
                    answer,
                ),
            ).toBeVisible();
            await expect(
                canvas.getByRole('list', { name: 'Surfaces' }),
            ).toBeVisible();
            await expect(
                server.sent('GET', `/routes/saved/${route?.id}/details`),
            ).toHaveLength(1);
        });

        await step('Copy its share link', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Copy link' }),
            );
            await expect(
                await canvas.findByRole('button', { name: 'Copied' }),
            ).toBeVisible();
            await expect(copies.copied).toEqual([route?.url]);
            await expect(
                canvas.getByRole('link', { name: 'Open' }),
            ).toHaveAttribute('href', route?.url);
        });

        await step('All routes goes back to the grid', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'All routes' }),
            );
            await waitFor(() => expect(shown(canvasElement)).toHaveLength(6));
        });
    },
};

/**
 * Delete removes the route at once and the library comes back without it. There is no confirm step in the page
 * today (`router.delete` on the first press).
 */
export const DeleteARoute: Story = {
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);

        await step('Open the walk and delete it', async () => {
            await open(canvasElement, 'Sunday walk to Heultje');
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Delete Sunday walk to Heultje',
                }),
            );
        });

        await step('The library comes back without it', async () => {
            await waitFor(
                () => expect(shown(canvasElement)).toHaveLength(5),
                answer,
            );
            await expect(shown(canvasElement)).not.toContain(
                'Sunday walk to Heultje',
            );
            await expect(
                server.sent('DELETE', /^\/routes\/saved\//),
            ).toHaveLength(1);
            await expect(server.library()).toHaveLength(5);
        });
    },
};

/** Search by name, then a sport chip, narrow the library; a search that matches nothing says so. */
export const FilterAndSearch: Story = {
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);
        const search = await canvas.findByRole('searchbox', {
            name: 'Search routes',
        });

        await step('Search for gravel', async () => {
            await userEvent.type(search, 'gravel');
            await expect(shown(canvasElement)).toEqual(['Grote Nete gravel']);
            await userEvent.clear(search);
            await expect(shown(canvasElement)).toHaveLength(6);
        });

        await step('Only the walks', async () => {
            const sport = within(canvas.getByRole('group', { name: 'Sport' }));
            await userEvent.click(sport.getByRole('button', { name: 'Walk' }));
            await expect(
                sport.getByRole('button', { name: 'Walk' }),
            ).toHaveAttribute('aria-pressed', 'true');
            await expect(shown(canvasElement)).toEqual([
                'Sunday walk to Heultje',
            ]);
            await userEvent.click(sport.getByRole('button', { name: 'All' }));
            await expect(shown(canvasElement)).toHaveLength(6);
        });

        await step('Nothing matches', async () => {
            await userEvent.type(search, 'Bruges');
            await expect(
                canvas.getByText('No saved routes match.'),
            ).toBeVisible();
        });
    },
};

/** Grid, list, and map. The arrow keys move along the layouts, and the library remembers the last one. */
export const SwitchLayouts: Story = {
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);
        const layouts = () =>
            within(
                canvas.getAllByRole('tablist', {
                    name: 'Layout',
                })[0] as HTMLElement,
            );

        await step('List', async () => {
            await userEvent.click(
                await layouts().findByRole('tab', { name: 'List' }),
            );
            await expect(
                layouts().getByRole('tab', { name: 'List' }),
            ).toHaveAttribute('aria-selected', 'true');
            await expect(canvasElement.querySelector('.list')).not.toBeNull();
            await expect(window.localStorage.getItem(layoutKey)).toBe('list');
        });

        // The layouts go Grid, Map, List; from the last, the arrow wraps round to the first.
        await step('Arrow right wraps round to the grid', async () => {
            await userEvent.keyboard('{ArrowRight}');
            await expect(
                layouts().getByRole('tab', { name: 'Grid' }),
            ).toHaveAttribute('aria-selected', 'true');
            await expect(
                layouts().getByRole('tab', { name: 'Grid' }),
            ).toHaveFocus();
            await expect(canvasElement.querySelector('.grid')).not.toBeNull();
        });

        await step(
            'Map puts every route on one map, with the list beside it',
            async () => {
                await userEvent.click(
                    layouts().getByRole('tab', { name: 'Map' }),
                );
                await expect(
                    await canvas.findByRole('complementary', {
                        name: 'Saved routes',
                    }),
                ).toBeVisible();
                await expect(window.localStorage.getItem(layoutKey)).toBe(
                    'map',
                );
            },
        );
    },
};

/** The gravel ride went to Wahoo before; Send to device sends it again, and the library reads where it went. */
export const SendFromTheLibrary: Story = {
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);

        await step('Open the gravel ride and pick Wahoo', async () => {
            await open(canvasElement, 'Grote Nete gravel');
            await userEvent.click(
                canvas.getByRole('button', { name: 'Send to device' }),
            );
            const dialog = within(
                await screen.findByRole('dialog', { name: 'Send to device' }),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Wahoo' }),
            );
        });

        await step('Send it again', async () => {
            const dialog = within(
                screen.getByRole('dialog', { name: 'Send to device' }),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Send to Wahoo again' }),
            );
            await expect(
                await dialog.findByText(/In your Wahoo app/, {}, answer),
            ).toBeVisible();
            // The library reloads its routes (`router.reload`), from the page's own address: here, the story's.
            await waitFor(
                () =>
                    expect(
                        server.requests.filter(
                            (sent) =>
                                sent.method === 'GET' &&
                                !sent.path.startsWith('/routes/'),
                        ),
                    ).toHaveLength(1),
                answer,
            );
        });
    },
};

/** A duathlon shows one land leg's climb at a time. */
export const MultisportRoute: Story = {
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);

        await step('Open the duathlon: leg 1 first', async () => {
            await open(canvasElement, 'Westerlo duathlon');
            await expect(
                canvas.getByRole('tab', { name: '1 Run' }),
            ).toHaveAttribute('aria-selected', 'true');
            await canvas.findByRole(
                'slider',
                { name: 'Height along the route' },
                answer,
            );
        });

        await step('Leg 2, the ride', async () => {
            await userEvent.click(canvas.getByRole('tab', { name: '2 Road' }));
            await expect(
                canvas.getByRole('tab', { name: '2 Road' }),
            ).toHaveAttribute('aria-selected', 'true');
            await waitFor(
                () =>
                    expect(
                        server.sent('GET', /\/details\?leg=2$/),
                    ).toHaveLength(1),
                answer,
            );
        });
    },
};

/** On a phone the library is a map with the routes in a sheet. */
export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
    play: async ({ canvasElement }) => {
        await expect(
            await within(canvasElement).findByText('6 routes'),
        ).toBeVisible();
    },
};
