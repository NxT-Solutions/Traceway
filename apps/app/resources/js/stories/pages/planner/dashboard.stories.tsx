import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, screen, userEvent, waitFor, within } from 'storybook/test';
import type { ServiceNoticeProps } from '@/components/service-notice';
import type { AvoidedRoad } from '@/lib/avoided-roads';
import { plannerDraftKey, recentPlacesKey } from '@/lib/planner-draft';
import Dashboard from '@/pages/dashboard';
import { rider } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { catchDownloads } from '../../support/planner-downloads';
import {
    avoidedRoads,
    type PlannerDraftOptions,
    plannerDraftDefaults,
    plannerDraftFor,
    routeServices,
    sportSurfaces,
} from '../../support/planner-pages';
import {
    noLoopFromThatPin,
    type PlannerServer,
    plannerServer,
} from '../../support/planner-server';
import { HttpResponse, http } from '../../support/server';

/** What the `dashboard` route in routes/web.php renders the planner with, besides the shared props. */
type DashboardProps = {
    serviceNotice: ServiceNoticeProps | null;
    sportSurfaces: Record<string, string>;
    showTour: boolean;
    avoided: AvoidedRoad[];
};

type PlannerArgs = PlannerDraftOptions & {
    /** Off: a new rider with nothing kept in this browser, on the default start in Ghent. */
    draft: boolean;
    /** The line above the map when route drawing is down. */
    serviceNotice: boolean;
    /** On: a rider's first visit, when the planner shows them around. */
    showTour: boolean;
    /** On: the rider avoids Zandstraat, a sandy track near Westerlo. */
    avoided: boolean;
};

const draftKey = plannerDraftKey(rider.id);
/** Keys the planner keeps in this browser besides the draft: the places picked lately and the device brand. */
const keptKeys = [recentPlacesKey(rider.id), 'traceway.send-to-device.brand'];

function forget(keys: string[]): void {
    try {
        for (const key of keys) {
            window.localStorage.removeItem(key);
        }
    } catch {
        // Storage can be blocked. Then nothing was kept.
    }
}

/** Writes the draft the planner opens on, the way it reopens after a rider drew a route and came back later. */
function keepDraft(args: PlannerArgs): void {
    try {
        if (args.draft) {
            window.localStorage.setItem(
                draftKey,
                JSON.stringify(plannerDraftFor(args)),
            );
        } else {
            window.localStorage.removeItem(draftKey);
        }
    } catch {
        // Storage can be blocked. The planner then opens empty.
    }
}

const notice: ServiceNoticeProps = {
    title: 'Route drawing is down right now.',
    body: 'It is a problem on the map server, not on your side. Please try again in a few minutes.',
};

/**
 * The planner, opened on the draft the Controls describe. A new draft remounts the page, since the planner reads
 * its draft once, on load.
 */
function Planner({ serviceNotice, showTour, avoided, ...args }: PlannerArgs) {
    keepDraft({ serviceNotice, showTour, avoided, ...args });

    return (
        <InertiaPage<DashboardProps>
            key={JSON.stringify({ ...args, showTour, avoided })}
            name="dashboard"
            component={Dashboard}
            url="/dashboard"
            props={{
                serviceNotice: serviceNotice ? notice : null,
                sportSurfaces,
                showTour,
                avoided: avoided ? avoidedRoads : [],
            }}
            shared={{ ...sharedProps(), routeServices }}
        />
    );
}

/** The mock server of the story that runs now, so its play function can check what the planner sent. */
let server: PlannerServer = plannerServer();

const sports = ['run', 'trail_run', 'walk', 'hike', 'road', 'gravel', 'mtb'];

/**
 * The planner at `/dashboard`: the map, the start pin, and the panel that sets the sport, the length, and the
 * roads. Mock Service Worker plays its server (`stories/support/planner-server.ts`): place search, snapping, three
 * loops scaled to the asked length, the climb and stops, saving, sending to a device, and course files. The loops
 * are one sketch of a loop near Westerlo, moved and scaled, never a plan from the routing engine. The Controls set
 * the draft the planner opens on. The map tiles come from OpenFreeMap, so the map needs a connection; the play
 * functions check the panel, not the map.
 */
const meta = {
    title: 'Pages/Planner/Dashboard',
    component: Planner,
    parameters: { layout: 'fullscreen' },
    args: {
        ...plannerDraftDefaults,
        draft: true,
        serviceNotice: false,
        showTour: false,
        avoided: false,
    },
    argTypes: {
        sport: { control: 'select', options: sports },
        distanceKm: {
            name: 'distance (km)',
            control: { type: 'range', min: 5, max: 200, step: 1 },
        },
        surface: {
            control: 'inline-radio',
            options: ['paved', 'mixed', 'dirt'],
        },
        elevation: {
            control: 'inline-radio',
            options: ['neutral', 'minimize', 'maximize'],
        },
        shape: { control: 'inline-radio', options: ['loop', 'out_and_back'] },
        roads: { control: 'inline-radio', options: ['quiet', 'any'] },
        mapStyle: {
            name: 'map style',
            control: 'select',
            options: ['auto', 'streets', 'light', 'dark'],
        },
        name: { control: 'text' },
        loops: { control: { type: 'range', min: 0, max: 3, step: 1 } },
        routeIndex: {
            name: 'chosen loop',
            control: { type: 'range', min: 0, max: 2, step: 1 },
        },
    },
    beforeEach: ({ msw, args }) => {
        server = plannerServer({ avoided: args.avoided ? avoidedRoads : [] });
        msw.use(...server.handlers);
        forget(keptKeys);

        return () => forget([draftKey, ...keptKeys]);
    },
} satisfies Meta<typeof Planner>;

export default meta;

type Story = StoryObj<typeof meta>;

/** How long to wait for the mock server, while the map loads its tiles over the same connection. */
const answer = { timeout: 5000 };
/** How long to wait for a save, which also draws a picture of the map. */
const slowAnswer = { timeout: 15_000 };

/** The panel, where every check of the planner happens. */
async function panelOf(canvasElement: HTMLElement): Promise<HTMLElement> {
    return within(canvasElement).findByRole(
        'complementary',
        { name: 'Route plan' },
        { timeout: 10_000 },
    );
}

/** A rider with no draft: the pin on the default start, nothing drawn yet. */
export const Empty: Story = {
    args: { draft: false },
};

/** The tour's card, which driver.js puts on the page body, outside the canvas. */
function tourCard(): Promise<HTMLElement> {
    return screen.findByRole('dialog', {}, { timeout: 10_000 });
}

/**
 * A new rider's first visit: the planner shows them around, one stop at a time, with the part it talks about lit
 * up. On a phone or a tablet the plan panel opens for the stops inside it and closes for the map and the header.
 * Finishing, or closing it early, tells the server, so the tour does not come back on its own. The question mark in
 * the header opens it again.
 */
export const FirstVisit: Story = {
    args: { draft: false, showTour: true },
    play: async ({ step }) => {
        await step('The tour opens on its own, focus on Show me', async () => {
            const card = await tourCard();
            await expect(card).toHaveAccessibleName('Welcome to Traceway');
            // The card fades in.
            await waitFor(() =>
                expect(within(card).getByText('1 of 10')).toBeVisible(),
            );
            await waitFor(() =>
                expect(
                    within(card).getByRole('button', { name: 'Show me' }),
                ).toHaveFocus(),
            );
        });

        await step('Next and Back step through the planner', async () => {
            await userEvent.click(
                within(await tourCard()).getByRole('button', {
                    name: 'Show me',
                }),
            );
            await waitFor(() =>
                expect(screen.getByRole('dialog')).toHaveTextContent(
                    'Find a place',
                ),
            );
            await userEvent.click(screen.getByRole('button', { name: 'Next' }));
            await waitFor(() =>
                expect(screen.getByRole('dialog')).toHaveTextContent(
                    'Or tap the map',
                ),
            );
            await userEvent.click(screen.getByRole('button', { name: 'Back' }));
            await waitFor(() =>
                expect(screen.getByRole('dialog')).toHaveTextContent(
                    'Find a place',
                ),
            );
        });

        await step('Every stop, to the end', async () => {
            for (const title of [
                'Or tap the map',
                'Three ways to plan',
                'Say what you want',
                'Draw your route',
                'Take it with you',
                'Your saved routes',
                'Your account',
                'Need it again?',
            ]) {
                await userEvent.click(
                    await screen.findByRole('button', { name: 'Next' }),
                );
                await waitFor(
                    () =>
                        expect(screen.getByRole('dialog')).toHaveTextContent(
                            title,
                        ),
                    answer,
                );
            }
            await userEvent.click(
                screen.getByRole('button', { name: 'Start planning' }),
            );
            await waitFor(() =>
                expect(screen.queryByRole('dialog')).toBeNull(),
            );
            await waitFor(() =>
                expect(server.sent('POST', '/dashboard/tour')).toHaveLength(1),
            );
        });

        await step('The question mark opens it again', async () => {
            await userEvent.click(
                screen.getByRole('button', { name: 'Show me around' }),
            );
            await expect(await tourCard()).toHaveAccessibleName(
                'Welcome to Traceway',
            );
            await userEvent.keyboard('{Escape}');
            await waitFor(() =>
                expect(screen.queryByRole('dialog')).toBeNull(),
            );
            // It was already noted once.
            await expect(server.sent('POST', '/dashboard/tour')).toHaveLength(
                1,
            );
        });
    },
};

/** The tour on a phone, where the plan panel is a drawer under the map. */
export const FirstVisitOnAPhone: Story = {
    args: { draft: false, showTour: true },
    globals: { viewport: { value: 'phone', isRotated: false } },
    play: async ({ canvasElement, step }) => {
        await step('The panel opens for the stops inside it', async () => {
            await userEvent.click(
                within(await tourCard()).getByRole('button', {
                    name: 'Show me',
                }),
            );
            for (const title of ['Or tap the map', 'Three ways to plan']) {
                await userEvent.click(
                    await screen.findByRole('button', { name: 'Next' }),
                );
                await waitFor(
                    () =>
                        expect(screen.getByRole('dialog')).toHaveTextContent(
                            title,
                        ),
                    answer,
                );
            }
            await expect(
                canvasElement.querySelector('[aria-label="Route plan"]'),
            ).toHaveClass('open');
        });
    },
};

/**
 * Three loops of about 10 km from Westerlo, the first one chosen. The planner asks the mock server for the climb,
 * the turns, and the stops along it.
 */
export const WithRoute: Story = {
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await step('The first of three loops is chosen', async () => {
            await expect(panel.getByText('Loop 1 of 3')).toBeVisible();
        });

        await step('The climb, the turns and the stops arrive', async () => {
            await expect(
                await panel.findByRole(
                    'slider',
                    { name: 'Height along the route' },
                    answer,
                ),
            ).toBeVisible();
            await expect(
                await panel.findByText('Turns (5)', {}, answer),
            ).toBeVisible();
            await expect(
                await panel.findByText('Stops on the way (3)', {}, answer),
            ).toBeVisible();
            await expect(
                server.sent('POST', '/routes/details'),
            ).not.toHaveLength(0);
            await expect(server.sent('POST', '/routes/stops')).not.toHaveLength(
                0,
            );
        });
    },
};

/** The same loops in Ink, with the dark map. */
export const WithRouteInInk: Story = {
    globals: { appearance: 'dark' },
};

/**
 * A rider picks a sport and a length. Each choice draws again: three loops come back, scaled to the length asked.
 */
export const PlanALoop: Story = {
    args: { loops: 0, sport: 'road', surface: 'paved', distanceKm: 20 },
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await step('Nothing is drawn yet', async () => {
            await expect(panel.getByText('Loop – of 3')).toBeVisible();
            await expect(
                panel.getByRole('button', { name: 'Draw route' }),
            ).toBeEnabled();
        });

        await step('Pick Trail run from the sports', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: /^Sport/ }),
            );
            const sheet = within(
                await screen.findByRole('dialog', { name: 'Choose a sport' }),
            );
            await userEvent.click(
                sheet.getByRole('button', { name: /^Trail run/ }),
            );
            await expect(
                await panel.findByText('Loop 1 of 3', {}, answer),
            ).toBeVisible();
            const asked = server.sent('POST', '/routes/preview').at(-1)?.body;
            await expect(asked).toMatchObject({
                sport: 'trail_run',
                // A trail run starts on dirt (Sport::defaultSurface).
                surface: 'dirt',
                distance_metres: 20_000,
            });
            await expect(
                panel.getByRole('button', { name: /^Sport/ }),
            ).toHaveTextContent('Trail run');
        });

        await step('Set the length to 12 km', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: /^Length/ }),
            );
            const sheet = within(
                await screen.findByRole('dialog', { name: 'Length' }),
            );
            const field = sheet.getByLabelText('Preferred length');
            await userEvent.clear(field);
            await userEvent.type(field, '12');
            await userEvent.click(sheet.getByRole('button', { name: 'Apply' }));
            await waitFor(
                () =>
                    expect(
                        server.sent('POST', '/routes/preview').at(-1)?.body,
                    ).toMatchObject({ distance_metres: 12_000 }),
                answer,
            );
            await expect(
                panel.getByRole('button', { name: /^Length/ }),
            ).toHaveTextContent('12 km');
            await waitFor(
                () =>
                    expect(
                        panel.getByRole('region', { name: 'Result' }),
                    ).toHaveTextContent(/12\.\d km/),
                answer,
            );
        });

        await step('Draw again asks for three new loops', async () => {
            const before = server.sent('POST', '/routes/preview').length;
            await userEvent.click(
                await panel.findByRole(
                    'button',
                    { name: 'Draw again' },
                    answer,
                ),
            );
            await waitFor(
                () =>
                    expect(server.sent('POST', '/routes/preview')).toHaveLength(
                        before + 1,
                    ),
                answer,
            );
            await expect(
                await panel.findByText('Loop 1 of 3', {}, answer),
            ).toBeVisible();
            // The button reads Drawing… until the new loops land, then eases back from its busy look; the story ends
            // once it has, so the accessibility check reads its colours at rest.
            const drawAgain = await panel.findByRole(
                'button',
                { name: 'Draw again' },
                answer,
            );
            await waitFor(() => expect(drawAgain).toBeEnabled(), answer);
            await waitFor(
                () => expect(getComputedStyle(drawAgain).opacity).toBe('1'),
                answer,
            );
        });
    },
};

/**
 * Next loop steps through the three loops and back to the first; each brings its own climb and turns. Previous loop
 * steps the other way.
 */
export const StepThroughLoops: Story = {
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));
        const next = panel.getByRole('button', { name: 'Next loop' });
        /** The different lines the planner asked details for so far. */
        const linesAsked = () =>
            new Set(
                server
                    .sent('POST', '/routes/details')
                    .map((sent) => JSON.stringify(sent.body?.coordinates)),
            ).size;

        await step('Loop 1 has its climb and turns', async () => {
            await expect(panel.getByText('Loop 1 of 3')).toBeVisible();
            await panel.findByText('Turns (5)', {}, answer);
            await waitFor(() => expect(linesAsked()).toBe(1), answer);
        });

        await step('Next loop: loop 2, with its own details', async () => {
            await userEvent.click(next);
            await expect(panel.getByText('Loop 2 of 3')).toBeVisible();
            await waitFor(() => expect(linesAsked()).toBe(2), answer);
        });

        await step('Loop 3, then round to loop 1', async () => {
            await userEvent.click(next);
            await expect(panel.getByText('Loop 3 of 3')).toBeVisible();
            await waitFor(() => expect(linesAsked()).toBe(3), answer);
            await userEvent.click(next);
            await expect(panel.getByText('Loop 1 of 3')).toBeVisible();
        });

        await step('Previous loop steps back, round to loop 3', async () => {
            const previous = panel.getByRole('button', {
                name: 'Previous loop',
            });

            await userEvent.click(previous);
            await expect(panel.getByText('Loop 3 of 3')).toBeVisible();
            await userEvent.click(previous);
            await expect(panel.getByText('Loop 2 of 3')).toBeVisible();
        });
    },
};

/** The pencil renames the route; Escape takes the old name back. The device name is the first 15 characters. */
export const RenameRoute: Story = {
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await step('Type a new name and press Enter', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: 'Rename route' }),
            );
            const field = panel.getByRole('textbox', { name: 'Route name' });
            await userEvent.clear(field);
            await userEvent.type(field, 'Tongerlo abbey loop{Enter}');
            await expect(
                panel.getByRole('heading', { name: 'Tongerlo abbey loop' }),
            ).toBeVisible();
            await expect(panel.getByText(/15 of 15 characters/)).toBeVisible();
        });

        await step('Escape keeps the name it had', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: 'Rename route' }),
            );
            const field = panel.getByRole('textbox', { name: 'Route name' });
            await userEvent.clear(field);
            await userEvent.type(field, 'Something else{Escape}');
            await expect(
                panel.getByRole('heading', { name: 'Tongerlo abbey loop' }),
            ).toBeVisible();
        });

        await step('The saved route carries the new name', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: 'Save to profile' }),
            );
            await expect(
                await panel.findByRole('button', { name: 'Saved' }, answer),
            ).toBeDisabled();
            await expect(server.library()[0]?.name).toBe('Tongerlo abbey loop');
        });
    },
};

/** Save to profile keeps the chosen loop in the library and makes its share link. */
export const SaveRoute: Story = {
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await step('Save the chosen loop', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: 'Save to profile' }),
            );
            await expect(
                await panel.findByRole('button', { name: 'Saved' }, answer),
            ).toBeDisabled();
            await expect(
                await panel.findByText(
                    'Saved. Share it from Saved routes.',
                    {},
                    answer,
                ),
            ).toBeVisible();
        });

        await step(
            'The server got the route and gave it a share link',
            async () => {
                const [saved] = server.sent('POST', '/routes/saved');
                await expect(saved?.body).toMatchObject({
                    name: 'Westerlo morning run',
                    sport: 'run',
                    surface: 'paved',
                });
                await expect(server.library()[0]?.url).toMatch(
                    /^\/share\/[0-9a-f-]{36}\?expires=\d+&signature=/,
                );
            },
        );
    },
};

/**
 * Send to device, Wahoo: the planner saves the route first, then sends it to the connected Wahoo account. Ride with
 * GPS is not connected, so the Karoo asks to connect it.
 */
export const SendToDevice: Story = {
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await step('Open Send to device and pick Wahoo', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: 'Send to device' }),
            );
            const dialog = within(
                await screen.findByRole('dialog', { name: 'Send to device' }),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Wahoo' }),
            );
            await expect(
                dialog.getByRole('button', { name: 'Wahoo' }),
            ).toHaveAttribute('aria-pressed', 'true');
        });

        await step('Send it in one tap', async () => {
            const dialog = within(
                screen.getByRole('dialog', { name: 'Send to device' }),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Send to Wahoo' }),
            );
            // Saving first draws the shared link's picture of the map in the
            // background, which takes a while on a busy CI runner.
            await expect(
                await dialog.findByText(/In your Wahoo app/, {}, slowAnswer),
            ).toBeVisible();
            const [saved] = server.sent('POST', '/routes/saved');
            const [sent] = server.sent('POST', /\/send\/wahoo$/);
            await expect(saved).toBeDefined();
            await expect(sent?.path).toBe(
                `/routes/saved/${server.library()[0]?.id}/send/wahoo`,
            );
            await expect(
                dialog.getByRole('button', { name: 'Send to Wahoo again' }),
            ).toBeVisible();
        });

        await step('A Karoo needs Ride with GPS connected first', async () => {
            const dialog = within(
                screen.getByRole('dialog', { name: 'Send to device' }),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Hammerhead Karoo' }),
            );
            await expect(
                dialog.getByRole('button', { name: 'Connect Ride with GPS' }),
            ).toBeVisible();
        });
    },
};

let downloads: ReturnType<typeof catchDownloads> = {
    files: [],
    restore: () => {},
};

/**
 * Export GPX builds the file in the browser, with the heights and stops; Download FIT asks the server for the
 * course. The story catches both files instead of saving them.
 */
export const ExportFiles: Story = {
    beforeEach: () => {
        downloads = catchDownloads();

        return downloads.restore;
    },
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await step('Export GPX', async () => {
            await panel.findByText('Turns (5)', {}, answer);
            await userEvent.click(
                panel.getByRole('button', { name: 'Export GPX' }),
            );
            const gpx = downloads.files.find(
                (file) => file.filename === 'traceway.gpx',
            );
            await expect(gpx?.blob?.type).toBe('application/gpx+xml');
            const body = (await gpx?.blob?.text()) ?? '';
            await expect(body).toContain('<name>Westerlo morning run</name>');
            await expect(body.match(/<trkpt /g)).toHaveLength(12);
        });

        await step('Download FIT for a Garmin', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: 'Send to device' }),
            );
            const dialog = within(
                await screen.findByRole('dialog', { name: 'Send to device' }),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Garmin' }),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Download FIT' }),
            );
            await waitFor(
                () =>
                    expect(
                        downloads.files.some(
                            (file) => file.filename === 'traceway.fit',
                        ),
                    ).toBe(true),
                answer,
            );
            await expect(
                server.sent('POST', '/routes/fit')[0]?.body,
            ).toMatchObject({ name: 'Westerlo morning run', sport: 'run' });
            const fit = downloads.files.find(
                (file) => file.filename === 'traceway.fit',
            );
            const bytes = new Uint8Array(
                (await fit?.blob?.arrayBuffer()) ?? new ArrayBuffer(0),
            );
            // A FIT file says so in bytes 8 to 11.
            await expect(String.fromCharCode(...bytes.slice(8, 12))).toBe(
                '.FIT',
            );
            await userEvent.keyboard('{Escape}');
        });

        await step('A file out asks for a hand with the hosting', async () => {
            await expect(
                await panel.findByText(
                    /Glad it helped\? Traceway is free\./,
                    {},
                    answer,
                ),
            ).toBeVisible();
        });
    },
};

/** Search finds Tongerlo through the server; the card on the map moves the start there. */
export const SearchAPlace: Story = {
    args: { loops: 0 },
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);
        const panel = within(await panelOf(canvasElement));

        await step('Ask for a new start', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: /^Start/ }),
            );
            const search = await canvas.findByRole('combobox', {
                name: 'Search for your start',
            });
            await waitFor(() => expect(search).toHaveFocus());
            await userEvent.type(search, 'Tongerlo{Enter}');
        });

        await step('The server finds the town', async () => {
            await expect(
                await canvas.findByRole(
                    'option',
                    { name: /^Tongerlo Westerlo, Antwerp, Belgium$/ },
                    answer,
                ),
            ).toBeVisible();
            await expect(
                server.sent('POST', '/routes/places')[0]?.body,
            ).toMatchObject({ query: 'Tongerlo' });
        });

        await step('Start there', async () => {
            await userEvent.click(
                canvas.getByRole('option', {
                    name: /^Tongerlo Westerlo, Antwerp, Belgium$/,
                }),
            );
            const card = within(
                await canvas.findByRole('dialog', { name: 'Tongerlo' }, answer),
            );
            await userEvent.click(
                await card.findByRole('button', { name: 'Start here' }, answer),
            );
            await expect(
                panel.getByRole('button', { name: /^Start/ }),
            ).toHaveTextContent('Tongerlo');
        });
    },
};

/**
 * A rider with a loop drawn searches for a place before the map has finished loading. The map frames the loop once
 * it loads, but not over the place the rider asked for, and the card asks what to do there as soon as the road is
 * found. It never waits on a road it stopped looking for.
 */
export const SearchWhileTheMapLoads: Story = {
    play: async ({ canvasElement, step }) => {
        const canvas = within(canvasElement);
        const panel = within(await panelOf(canvasElement));

        await step('Search for Tongerlo at once', async () => {
            await userEvent.click(
                panel.getByRole('button', { name: /^Start/ }),
            );
            const search = await canvas.findByRole('combobox', {
                name: 'Search for your start',
            });
            await userEvent.type(search, 'Tongerlo{Enter}');
            await userEvent.click(
                await canvas.findByRole(
                    'option',
                    { name: /^Tongerlo Westerlo, Antwerp, Belgium$/ },
                    answer,
                ),
            );
        });

        await step('The card offers to start there', async () => {
            const card = within(
                await canvas.findByRole('dialog', { name: 'Tongerlo' }, answer),
            );
            await expect(
                await card.findByRole('button', { name: 'Start here' }, answer),
            ).toBeVisible();
        });
    },
};

/** Multisport draws a brick from the transition: a 40 km ride, then a 5 km run, each leg with its own climb. */
export const Multisport: Story = {
    args: { loops: 0 },
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await step('Switch to Multisport and draw', async () => {
            await userEvent.click(
                panel.getByRole('tab', { name: 'Multisport' }),
            );
            await userEvent.click(
                panel.getByRole('button', { name: 'Draw route' }),
            );
            const asked = await waitFor(() => {
                const [sent] = server.sent('POST', '/routes/multisport');
                expect(sent).toBeDefined();

                return sent?.body;
            }, answer);
            await expect(asked).toMatchObject({
                layout: 'loops',
                legs: [
                    { sport: 'road', distance_metres: 40_000 },
                    { sport: 'run', distance_metres: 5000 },
                ],
            });
        });

        await step('Both legs come back, in order', async () => {
            const result = within(
                panel.getByRole('region', { name: 'Result' }),
            );
            await waitFor(
                () => expect(result.getAllByRole('listitem')).toHaveLength(2),
                answer,
            );
            const [ride, run] = result.getAllByRole('listitem');
            await expect(ride).toHaveTextContent(/Road\s*40\.\d km/);
            await expect(run).toHaveTextContent(/Run\s*5\.\d km/);
        });

        await step('Show the run leg’s climb', async () => {
            const tab = await panel.findByRole(
                'tab',
                { name: '2 Run' },
                answer,
            );
            await userEvent.click(tab);
            await expect(tab).toHaveAttribute('aria-selected', 'true');
        });
    },
};

/** The routing engine found nothing: the planner says why, and the rider can try again. */
export const NoLoopCameBack: Story = {
    args: { loops: 0 },
    beforeEach: ({ msw }) => {
        msw.use(noLoopFromThatPin);
    },
    play: async ({ canvasElement }) => {
        const panel = within(await panelOf(canvasElement));

        await userEvent.click(
            panel.getByRole('button', { name: 'Draw route' }),
        );
        await expect(
            await panel.findByText(
                'No loop came back from that pin. Try another spot or a different distance.',
                {},
                answer,
            ),
        ).toBeVisible();
        await expect(
            panel.getByRole('button', { name: 'Draw route' }),
        ).toBeEnabled();
    },
};

/** The map's own keyboard: Enter taps the cross in the middle, wherever a tap would land. */
async function tapMapCentre(canvasElement: HTMLElement): Promise<void> {
    const map = await within(canvasElement).findByLabelText(
        /^Map\. Arrow keys move it/,
        {},
        { timeout: 10_000 },
    );

    map.focus();
    await userEvent.keyboard('{Enter}');
}

/**
 * A track that is mapped fine but rides badly. The rider taps Avoid a road, then the road: Traceway keeps it for
 * them, says so, and plans the loops again around it. Undo lets it back in.
 */
export const AvoidARoad: Story = {
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await expect(
            await panel.findByText('Loop 1 of 3', {}, answer),
        ).toBeVisible();

        await step('Avoid a road waits for a tap on the map', async () => {
            const pick = panel.getByRole('button', { name: 'Avoid a road' });
            await userEvent.click(pick);
            await expect(pick).toHaveAttribute('aria-pressed', 'true');
        });

        await step('A tap on the road avoids it and plans again', async () => {
            const before = server.sent('POST', '/routes/preview').length;
            await tapMapCentre(canvasElement);
            await expect(
                await panel.findByRole(
                    'button',
                    { name: 'Remove Netepad' },
                    answer,
                ),
            ).toBeVisible();
            await expect(
                server.sent('POST', '/routes/avoided').at(-1)?.body,
            ).toMatchObject({
                sport: plannerDraftDefaults.sport,
                surface: plannerDraftDefaults.surface,
            });
            await waitFor(
                () =>
                    expect(server.sent('POST', '/routes/preview')).toHaveLength(
                        before + 1,
                    ),
                answer,
            );
            await expect(
                await within(canvasElement).findByRole(
                    'group',
                    { name: 'Last change' },
                    answer,
                ),
            ).toHaveTextContent('Avoiding Netepad');
        });

        await step('Undo lets the road back in', async () => {
            await userEvent.click(
                await within(canvasElement).findByRole(
                    'button',
                    { name: 'Undo' },
                    answer,
                ),
            );
            await waitFor(
                () =>
                    expect(
                        server.sent('DELETE', /^\/routes\/avoided\//),
                    ).toHaveLength(1),
                answer,
            );
            await waitFor(
                () => expect(server.avoided()).toHaveLength(0),
                answer,
            );
            await expect(
                panel.queryByRole('button', { name: 'Remove Netepad' }),
            ).not.toBeInTheDocument();
        });
    },
};

/**
 * A rider who avoids Zandstraat lets it back in from the panel, and the loops are drawn again. Undo avoids it once
 * more, for the gravel ride it was first found on, whatever the planner is set to now.
 */
export const StopAvoiding: Story = {
    args: { avoided: true },
    play: async ({ canvasElement, step }) => {
        const panel = within(await panelOf(canvasElement));

        await expect(
            await panel.findByText('Loop 1 of 3', {}, answer),
        ).toBeVisible();

        await step('Remove lets it back into every plan', async () => {
            const before = server.sent('POST', '/routes/preview').length;
            await userEvent.click(
                panel.getByRole('button', { name: 'Remove Zandstraat' }),
            );
            await expect(
                await within(canvasElement).findByRole(
                    'group',
                    { name: 'Last change' },
                    answer,
                ),
            ).toHaveTextContent('Zandstraat back in your plans');
            await expect(server.avoided()).toHaveLength(0);
            await waitFor(
                () =>
                    expect(server.sent('POST', '/routes/preview')).toHaveLength(
                        before + 1,
                    ),
                answer,
            );
            await expect(
                panel.getByText(
                    /Avoid it, and Traceway skips it on every plan/,
                ),
            ).toBeVisible();
        });

        await step(
            'Undo avoids it again, for the sport it was found for',
            async () => {
                await userEvent.click(
                    await within(canvasElement).findByRole(
                        'button',
                        { name: 'Undo' },
                        answer,
                    ),
                );
                await expect(
                    await panel.findByRole(
                        'button',
                        { name: 'Remove Zandstraat' },
                        answer,
                    ),
                ).toBeVisible();
                await waitFor(
                    () =>
                        expect(
                            server.sent('POST', '/routes/avoided').at(-1)?.body,
                        ).toMatchObject({ sport: 'gravel', surface: 'mixed' }),
                    answer,
                );
                await waitFor(
                    () => expect(server.avoided()).toHaveLength(1),
                    answer,
                );
            },
        );
    },
};

/** Far from any road the rider can use: the planner says so and keeps the loops as they were. */
export const NoRoadToAvoid: Story = {
    beforeEach: ({ msw }) => {
        msw.use(
            http.post('/routes/avoided', () =>
                HttpResponse.json(
                    {
                        message:
                            'No road or path is close enough there. Tap right on the road.',
                    },
                    { status: 422 },
                ),
            ),
        );
    },
    play: async ({ canvasElement }) => {
        const panel = within(await panelOf(canvasElement));

        await expect(
            await panel.findByText('Loop 1 of 3', {}, answer),
        ).toBeVisible();
        const before = server.sent('POST', '/routes/preview').length;
        await userEvent.click(
            panel.getByRole('button', { name: 'Avoid a road' }),
        );
        await tapMapCentre(canvasElement);
        await expect(
            await panel.findByText(
                'No road or path is close enough there. Tap right on the road.',
                {},
                answer,
            ),
        ).toBeVisible();
        await expect(server.sent('POST', '/routes/preview')).toHaveLength(
            before,
        );
    },
};

/** On a phone the panel becomes a sheet under the map; its handle opens and closes it. */
export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
    play: async ({ canvasElement }) => {
        const panel = within(await panelOf(canvasElement));
        const handle = panel.getByRole('button', {
            name: /^(Expand|Collapse) the plan$/,
        });
        const open = handle.getAttribute('aria-expanded') === 'true';

        await expect(
            panel.getByRole('button', { name: /Loop 1 of 3/ }),
        ).toBeVisible();
        await userEvent.click(handle);
        await waitFor(() =>
            expect(handle).toHaveAttribute('aria-expanded', String(!open)),
        );
    },
};

/** A line above the map when route drawing is down, from the status checks. */
export const ServiceNotice: Story = {
    args: { serviceNotice: true },
};
