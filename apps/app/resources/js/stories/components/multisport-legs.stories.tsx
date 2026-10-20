import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { expect, fn, within } from 'storybook/test';
import {
    LegColourPicker,
    MultisportLegs,
    type Placing,
} from '@/components/multisport-legs';
import {
    type ColourSet,
    coloursOf,
    type LandLeg,
    type Layout,
    legColour,
} from '@/lib/multisport';
import type { Coordinate } from '@/lib/route-arrows';
import { PlannerScene } from '../support/planner-scene';

const sportLabels: Record<string, string> = {
    run: 'Run',
    trail_run: 'Trail run',
    road: 'Road',
    gravel: 'Gravel',
    mtb: 'Mountain bike',
};
const surfaceLabels: Record<string, string> = {
    mixed: 'Any',
    paved: 'Paved',
    dirt: 'Dirt',
};
const elevationLabels: Record<string, string> = {
    neutral: 'Any',
    minimize: 'Flat',
    maximize: 'Hilly',
};
const roadLabels: Record<string, string> = {
    quiet: 'Quiet first',
    any: 'Any road',
};

const labels = {
    sport: (value: string) => sportLabels[value] ?? value,
    surface: (value: string) => surfaceLabels[value] ?? value,
    elevation: (value: string) => elevationLabels[value] ?? value,
    roads: (value: string) => roadLabels[value] ?? value,
};

const ride: LandLeg = {
    id: 'ride',
    sport: 'road',
    distanceKm: 40,
    surface: 'paved',
    elevation: 'neutral',
    roads: 'quiet',
    finish: null,
};

const run: LandLeg = {
    id: 'run',
    sport: 'run',
    distanceKm: 5,
    surface: 'mixed',
    elevation: 'minimize',
    roads: 'quiet',
    finish: null,
};

const brick = [ride, run];

/** A swim across the lake at De Merode, by Westerlo. */
const lakeSwim: Coordinate[] = [
    [4.9558, 51.0702],
    [4.9611, 51.0718],
    [4.9662, 51.0709],
    [4.9603, 51.0694],
];

/** What the planner hears from the legs list, logged in the Actions panel. */
type LegsCallbacks = {
    onLayout: (value: Layout) => void;
    onSwim: (enabled: boolean) => void;
    onDrawSwim: () => void;
    onSelect: (index: number) => void;
    onOpen: (sheet: string) => void;
    onAdd: () => void;
    onRemove: (index: number) => void;
    onMove: (from: number, to: number) => void;
    onPlaceFinish: (index: number) => void;
    onFindTransition: () => void;
};

/** The legs list with its own state, the way the planner holds it. */
function Legs({
    legs: initialLegs,
    layout: initialLayout,
    swim: initialSwim,
    colourSet,
    swimTargetKm,
    transitionName,
    placing,
    ...on
}: {
    legs: LandLeg[];
    layout: Layout;
    swim: Coordinate[] | null;
    colourSet: ColourSet;
    swimTargetKm: number | null;
    transitionName: string | null;
    placing: Placing;
} & LegsCallbacks) {
    const [legs, setLegs] = useState(initialLegs);
    const [layout, setLayout] = useState(initialLayout);
    const [swim, setSwim] = useState(initialSwim);
    const [activeLeg, setActiveLeg] = useState(0);
    const offset = swim === null ? 0 : 1;
    const colours = [
        ...(swim === null ? [] : [legColour(colourSet, 0)]),
        ...legs.map((leg, index) =>
            legColour(colourSet, index + offset, leg.color),
        ),
    ];

    return (
        <div
            className="panel open"
            style={{
                position: 'relative',
                inset: 'auto',
                width: 'min(400px, 100%)',
                height: 'auto',
                maxHeight: 'none',
            }}
        >
            <div className="pbody">
                <MultisportLegs
                    legs={legs}
                    layout={layout}
                    swim={swim}
                    activeLeg={activeLeg}
                    placing={placing}
                    labels={labels}
                    colours={colours}
                    colourSet={colourSet}
                    swimTargetKm={swimTargetKm}
                    onLayout={(value) => {
                        on.onLayout(value);
                        setLayout(value);
                    }}
                    onSwim={(enabled) => {
                        on.onSwim(enabled);
                        setSwim(enabled ? [] : null);
                    }}
                    onDrawSwim={on.onDrawSwim}
                    onSelect={(index) => {
                        on.onSelect(index);
                        setActiveLeg(index);
                    }}
                    onOpen={on.onOpen}
                    onAdd={() => {
                        on.onAdd();
                        setLegs((current) => [
                            ...current,
                            {
                                id: `leg-${current.length + 1}`,
                                sport: 'run',
                                distanceKm: 5,
                                surface: 'mixed',
                                elevation: 'neutral',
                                roads: 'quiet',
                                finish: null,
                            },
                        ]);
                    }}
                    onRemove={(index) => {
                        on.onRemove(index);
                        setLegs((current) =>
                            current.filter((_, at) => at !== index),
                        );
                    }}
                    onMove={(from, to) => {
                        on.onMove(from, to);
                        setLegs((current) => {
                            const next = [...current];
                            const [moved] = next.splice(from, 1);

                            if (moved !== undefined) {
                                next.splice(to, 0, moved);
                            }

                            return next;
                        });
                    }}
                    onPlaceFinish={on.onPlaceFinish}
                    transitionName={transitionName}
                    onFindTransition={on.onFindTransition}
                />
            </div>
        </div>
    );
}

/** The colour sheet with its own state: a swim, a ride, and a run. */
function ColourPickerDemo({
    colourSet,
    onSet,
    onPick,
}: {
    colourSet: ColourSet;
    onSet: (value: ColourSet) => void;
    onPick: (index: number, hex: string) => void;
}) {
    const [set, setSet] = useState<ColourSet>(colourSet);
    const [legs, setLegs] = useState([
        { label: 'Swim', colour: legColour(colourSet, 0) },
        { label: 'Road', colour: legColour(colourSet, 1) },
        { label: 'Run', colour: legColour(colourSet, 2) },
    ]);

    return (
        <div style={{ maxWidth: 400 }}>
            <LegColourPicker
                colourSet={set}
                palette={coloursOf(set)}
                legs={legs}
                onSet={(next) => {
                    onSet(next);
                    setSet(next);
                    setLegs((current) =>
                        current.map((leg, index) => ({
                            ...leg,
                            colour: legColour(next, index),
                        })),
                    );
                }}
                onPick={(index, hex) => {
                    onPick(index, hex);
                    setLegs((current) =>
                        current.map((leg, at) =>
                            at === index ? { ...leg, colour: hex } : leg,
                        ),
                    );
                }}
            />
        </div>
    );
}

const callback = (description: string) => ({
    description,
    control: false as const,
});

/**
 * The legs of a brick, duathlon, or triathlon, in order, and the settings of the leg being edited. Each leg has
 * its own colour on the map. Drag a leg by its grip, or use the arrow keys on it, to change the order.
 */
const meta = {
    title: 'Components/MultisportLegs',
    component: Legs,
    args: {
        legs: brick,
        layout: 'loops',
        swim: null,
        colourSet: 'bold',
        swimTargetKm: null,
        transitionName: 'Westerlo',
        placing: null,
        onLayout: fn(),
        onSwim: fn(),
        onDrawSwim: fn(),
        onSelect: fn(),
        onOpen: fn(),
        onAdd: fn(),
        onRemove: fn(),
        onMove: fn(),
        onPlaceFinish: fn(),
        onFindTransition: fn(),
    },
    argTypes: {
        legs: {
            control: 'object',
            description:
                'The land legs in order: sport, distance, surface, hills, roads, and in a chain where each finishes.',
        },
        layout: {
            control: 'inline-radio',
            options: ['loops', 'chain'],
            description:
                '`loops`: every leg is a loop from the transition. `chain`: each leg ends where the next starts.',
        },
        swim: {
            control: 'object',
            description:
                'The swim drawn on the water, as [lng, lat] points, or null for no swim.',
        },
        colourSet: {
            control: 'inline-radio',
            options: ['bold', 'colour_blind', 'night', 'earth'],
            description: 'The colours the legs take on the map.',
        },
        swimTargetKm: {
            control: { type: 'number', min: 0, step: 0.1 },
            description: 'How far the swim of the chosen race is.',
        },
        transitionName: {
            control: 'text',
            description: 'The place around the transition, once it has a name.',
        },
        placing: {
            control: 'object',
            description:
                'What a tap on the map sets now: the swim, or the finish of a leg.',
        },
        onLayout: callback('Loops from T or Chain was chosen.'),
        onSwim: callback('A swim was added (true) or removed (false).'),
        onDrawSwim: callback('Draw the swim on the map, or stop.'),
        onSelect: callback('A leg was chosen to edit.'),
        onOpen: callback('A setting row opened its sheet.'),
        onAdd: callback('Add a leg.'),
        onRemove: callback('Remove the leg at this index.'),
        onMove: callback('A leg moved from one place to another.'),
        onPlaceFinish: callback("Tap the map for this leg's finish."),
        onFindTransition: callback('Search for where the transition is.'),
    },
    parameters: { layout: 'fullscreen' },
    // The legs keep their own state; a change in the controls starts them again.
    render: (args) => <Legs key={JSON.stringify(args)} {...args} />,
    decorators: [
        (Story) => (
            <PlannerScene>
                <Story />
            </PlannerScene>
        ),
    ],
} satisfies Meta<typeof Legs>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A ride, then a run straight off the bike. Choose a leg, change its order, add one and take it away. */
export const Brick: Story = {
    play: async ({ canvas, args, userEvent, step }) => {
        const legs = within(canvas.getByRole('region', { name: 'Legs' }));

        await expect(
            legs.getByRole('button', { name: /^Road/ }),
        ).toHaveAttribute('aria-pressed', 'true');

        await step('Choose the run', async () => {
            await userEvent.click(legs.getByRole('button', { name: /^Run/ }));
            await expect(args.onSelect).toHaveBeenCalledWith(1);
            await expect(
                legs.getByRole('button', { name: /^Run/ }),
            ).toHaveAttribute('aria-pressed', 'true');
            await expect(
                canvas.getByRole('region', { name: 'Leg 2' }),
            ).toBeVisible();
        });

        await step('Move it earlier', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Move this leg earlier' }),
            );
            await expect(args.onMove).toHaveBeenCalledWith(1, 0);
            await expect(
                legs.getAllByRole('button', { name: /Edit this leg/ })[0],
            ).toHaveAccessibleName(/^Run/);
        });

        await step('Open its sport', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: /^Sport/ }),
            );
            await expect(args.onOpen).toHaveBeenCalledWith('sport');
        });

        await step('Add a leg, then take it away', async () => {
            await userEvent.click(
                legs.getByRole('button', { name: 'Add a leg' }),
            );
            await expect(args.onAdd).toHaveBeenCalledOnce();
            await expect(
                legs.getAllByRole('button', { name: /Edit this leg/ }),
            ).toHaveLength(3);
            await userEvent.click(
                legs.getByRole('button', { name: 'Remove leg 3' }),
            );
            await expect(args.onRemove).toHaveBeenCalledWith(2);
            await expect(
                legs.getAllByRole('button', { name: /Edit this leg/ }),
            ).toHaveLength(2);
        });

        await step('Chain the legs', async () => {
            const chain = canvas.getByRole('radio', { name: 'Chain' });

            await userEvent.click(chain);
            await expect(args.onLayout).toHaveBeenCalledWith('chain');
            await expect(chain).toHaveAttribute('aria-checked', 'true');
        });
    },
};

/** An Olympic-distance triathlon with the swim drawn on the lake. */
export const Triathlon: Story = {
    args: {
        swim: lakeSwim,
        swimTargetKm: 1.5,
        legs: [
            { ...ride, distanceKm: 40 },
            { ...run, distanceKm: 10 },
        ],
    },
    play: async ({ canvas, args, userEvent }) => {
        const legs = within(canvas.getByRole('region', { name: 'Legs' }));

        await userEvent.click(legs.getByRole('button', { name: /^Swim/ }));
        await expect(args.onDrawSwim).toHaveBeenCalledOnce();

        await userEvent.click(
            legs.getByRole('button', { name: 'Remove the swim' }),
        );
        await expect(args.onSwim).toHaveBeenCalledWith(false);
        await expect(
            legs.getByRole('button', { name: 'Add a swim' }),
        ).toBeVisible();
    },
};

/** In a chain, each leg ends where the next one starts. */
export const Chain: Story = {
    args: {
        layout: 'chain',
        placing: { kind: 'finish', leg: 0 },
        legs: [
            { ...ride, finish: [5.0012, 51.0764], stretch: true },
            { ...run, finish: null },
        ],
    },
    play: async ({ canvas, args, userEvent }) => {
        const finish = canvas.getByRole('button', { name: /^Finish/ });

        await expect(finish).toHaveTextContent('Tap the map');
        await userEvent.click(finish);
        await expect(args.onPlaceFinish).toHaveBeenCalledWith(0);
    },
};

/** Before the transition has a name, its row asks where it is. */
export const NoTransitionYet: Story = {
    args: { transitionName: null },
    play: async ({ canvas, args, userEvent }) => {
        const transition = canvas.getByRole('button', {
            name: /change the transition/i,
        });

        await expect(transition).toHaveTextContent('Transition');
        await userEvent.click(transition);
        await expect(args.onFindTransition).toHaveBeenCalledOnce();
    },
};

/** Pick a colour set for the plan, then change any one leg. Two legs in one colour get a warning. */
export const ColourPicker: StoryObj<typeof ColourPickerDemo> = {
    args: { onSet: fn(), onPick: fn() },
    render: (args) => <ColourPickerDemo {...args} />,
    play: async ({ canvas, args, userEvent, step }) => {
        await step('Choose the Night set', async () => {
            const night = canvas.getByRole('radio', { name: /^Night/ });

            await userEvent.click(night);
            await expect(args.onSet).toHaveBeenCalledWith('night');
            await expect(night).toHaveAttribute('aria-checked', 'true');
        });

        await step('Give the ride the colour of the swim', async () => {
            const swimColour = within(
                canvas.getByRole('radiogroup', {
                    name: 'Colour for leg 1, Swim',
                }),
            )
                .getAllByRole('radio')
                .find((radio) => radio.getAttribute('aria-checked') === 'true');
            const road = within(
                canvas.getByRole('radiogroup', {
                    name: 'Colour for leg 2, Road',
                }),
            );

            await userEvent.click(
                road.getByRole('radio', {
                    name: swimColour?.getAttribute('aria-label') ?? '',
                }),
            );
            await expect(args.onPick).toHaveBeenCalledWith(
                1,
                expect.any(String),
            );
            await expect(canvas.getByRole('status')).toHaveTextContent(
                'Two legs share a colour.',
            );
        });
    },
};
