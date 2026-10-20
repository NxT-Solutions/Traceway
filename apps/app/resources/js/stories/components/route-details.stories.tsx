import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { expect, fn } from 'storybook/test';
import {
    RouteCues,
    RouteProfile,
    SurfaceBar,
} from '@/components/route-details';
import type { Coordinate } from '@/lib/route-arrows';
import type { RouteDetails } from '@/lib/route-details';
import { PlannerScene } from '../support/planner-scene';

/** Heights along a 41.6 km loop from Westerlo: low polders, two short climbs, and the drop back to the Nete. */
const profile: [number, number][] = Array.from({ length: 105 }, (_, index) => {
    const metres = Math.min(41_600, index * 400);
    const height =
        14 +
        18 * Math.sin((metres / 41_600) * Math.PI) +
        9 * Math.sin((metres / 41_600) * Math.PI * 6) ** 2 +
        4 * Math.sin((metres / 41_600) * Math.PI * 19);

    return [metres, Math.round(height * 10) / 10];
});

const morningLoop: RouteDetails = {
    profile,
    ascent_metres: 180,
    descent_metres: 180,
    surfaces: [
        { surface: 'paved', metres: 36_200 },
        { surface: 'gravel', metres: 4_100 },
        { surface: 'dirt', metres: 900 },
        { surface: 'unknown', metres: 400 },
    ],
    cues: [
        {
            distance_metres: 400,
            longitude: 4.9172,
            latitude: 51.0893,
            turn: 'left',
            street: 'Boerenkrijgsingel',
        },
        {
            distance_metres: 2_100,
            longitude: 4.9241,
            latitude: 51.0951,
            turn: 'right',
            street: 'Grote Markt',
        },
        {
            distance_metres: 9_800,
            longitude: 4.9688,
            latitude: 51.1187,
            turn: 'slight_left',
            street: 'Netestraat',
        },
        {
            distance_metres: 18_300,
            longitude: 5.0012,
            latitude: 51.0764,
            turn: 'straight',
            street: 'Second exit',
        },
        {
            distance_metres: 29_700,
            longitude: 4.9543,
            latitude: 51.0512,
            turn: 'sharp_right',
            street: null,
        },
        {
            distance_metres: 41_200,
            longitude: 4.9161,
            latitude: 51.0889,
            turn: 'u_turn',
            street: null,
        },
    ],
    stops: [
        {
            distance_metres: 12_400,
            longitude: 4.9781,
            latitude: 51.1123,
            kind: 'water',
            name: null,
        },
        {
            distance_metres: 21_900,
            longitude: 4.9934,
            latitude: 51.0687,
            kind: 'cafe',
            name: 'Café De Zwaan',
        },
        {
            distance_metres: 33_500,
            longitude: 4.9412,
            latitude: 51.0598,
            kind: 'toilet',
            name: null,
        },
    ],
};

/** What the planner hears from the details, logged in the Actions panel. */
type DetailsCallbacks = {
    onHover: (metres: number | null) => void;
    onSteepness: (on: boolean) => void;
    onFocusPoint: (point: Coordinate | null) => void;
    onSelectPoint: (point: Coordinate) => void;
};

/** All three parts, the way the planner shows them under a finished route. */
function Details({
    details,
    onHover,
    onSteepness,
    onFocusPoint,
    onSelectPoint,
}: { details: RouteDetails } & DetailsCallbacks) {
    const [steepness, setSteepness] = useState(false);

    return (
        <div className="tw-details">
            <RouteProfile
                details={details}
                onHover={onHover}
                steepness={steepness}
                onSteepness={(on) => {
                    onSteepness(on);
                    setSteepness(on);
                }}
            />
            <SurfaceBar details={details} />
            <RouteCues
                details={details}
                onFocusPoint={onFocusPoint}
                onSelectPoint={onSelectPoint}
            />
        </div>
    );
}

/**
 * How a finished route climbs, what it runs on, where it turns, and where to stop. Move over the profile, or use
 * the arrow keys on it, to read the height at a distance; the planner marks the same place on the map. Choosing a
 * turn or a stop takes the map there.
 */
const meta = {
    title: 'Components/RouteDetails',
    component: Details,
    args: {
        details: morningLoop,
        onHover: fn(),
        onSteepness: fn(),
        onFocusPoint: fn(),
        onSelectPoint: fn(),
    },
    argTypes: {
        details: {
            control: 'object',
            description:
                'The heights along the route, metres up and down, the surfaces, the turns, and the stops on the way.',
        },
        onHover: {
            description:
                'The distance being read on the profile, or null when the rider moves away. The planner marks it on the map.',
        },
        onSteepness: {
            description: 'Steepness on map was ticked or cleared.',
        },
        onFocusPoint: {
            description: 'A turn or a stop is pointed at or focused, or null.',
        },
        onSelectPoint: {
            description: 'A turn or a stop was chosen: the map goes there.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <PlannerScene>
                <div style={{ maxWidth: 360 }}>
                    <Story />
                </div>
            </PlannerScene>
        ),
    ],
} satisfies Meta<typeof Details>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Tick the steepness, read the profile with the arrow keys, and choose a turn and a stop. */
export const MorningLoop: Story = {
    play: async ({ canvas, args, userEvent, step }) => {
        await step('Show the steepness', async () => {
            await userEvent.click(canvas.getByLabelText('Steepness on map'));
            await expect(args.onSteepness).toHaveBeenCalledWith(true);
            await expect(
                canvas.getByRole('list', { name: 'Steepness' }),
            ).toBeVisible();
        });

        await step('Read the height with the arrow keys', async () => {
            const chart = canvas.getByRole('slider', {
                name: 'Height along the route',
            });

            await expect(chart.getAttribute('aria-valuetext')).toMatch(
                /km, climbing 180 metres$/,
            );
            await userEvent.tab();
            await expect(chart).toHaveFocus();
            await userEvent.keyboard('{ArrowRight}{ArrowRight}');
            await expect(chart.getAttribute('aria-valuetext')).toMatch(
                /^[\d.]+ k?m, \d+ metres high$/,
            );
            await expect(args.onHover).toHaveBeenCalledWith(expect.any(Number));
            await userEvent.keyboard('{Escape}');
            await expect(args.onHover).toHaveBeenLastCalledWith(null);
        });

        await step('Choose a turn', async () => {
            await userEvent.click(canvas.getByText('Turns (6)'));
            await userEvent.click(
                canvas.getByRole('button', { name: /Grote Markt/ }),
            );
            await expect(args.onSelectPoint).toHaveBeenCalledWith([
                4.9241, 51.0951,
            ]);
        });

        await step('Choose a stop', async () => {
            await userEvent.click(canvas.getByText('Stops on the way (3)'));
            await userEvent.click(
                canvas.getByRole('button', { name: /Café De Zwaan/ }),
            );
            await expect(args.onSelectPoint).toHaveBeenLastCalledWith([
                4.9934, 51.0687,
            ]);
            await expect(args.onFocusPoint).toHaveBeenCalled();
        });
    },
};

/** The profile alone, with the steepness key, as the route library shows it. */
export const ProfileWithSteepness: Story = {
    render: (args) => (
        <div className="tw-details">
            <RouteProfile details={args.details} steepness />
        </div>
    ),
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('list', { name: 'Steepness' }),
        ).toBeVisible();
        await expect(
            canvas.queryByLabelText('Steepness on map'),
        ).not.toBeInTheDocument();
    },
};

/** A flat route still gets some room, so ten metres of rise is not a cliff. */
export const Flat: Story = {
    args: {
        details: {
            ...morningLoop,
            profile: profile.map(([metres]) => [
                metres,
                6 + 2 * Math.sin(metres / 3_000),
            ]),
            ascent_metres: 24,
            descent_metres: 24,
            surfaces: [{ surface: 'paved', metres: 41_600 }],
            stops: [],
        },
    },
    play: async ({ canvas }) => {
        await expect(canvas.getAllByText('24 m')).toHaveLength(2);
        await expect(
            canvas.queryByText(/Stops on the way/),
        ).not.toBeInTheDocument();
    },
};
