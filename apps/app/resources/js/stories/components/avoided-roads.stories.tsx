import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import { AvoidedRoads } from '@/components/avoided-roads';
import { avoidedRoads } from '../support/planner-pages';
import { PlannerScene } from '../support/planner-scene';

const unnamed = {
    id: '0199f2a4-6c1b-7d3e-9a8f-4b4c6d8e0f22',
    name: null,
    latitude: 51.1012,
    longitude: 4.9312,
    shape: [
        [4.9301, 51.1008],
        [4.9323, 51.1016],
    ] as [number, number][],
};

/**
 * The roads a rider skips, in the planner's panel. "Avoid a road" waits for the next tap on the map and skips the
 * way under it on every plan from then on, private to the rider. Each road can be shown on the map or let back in.
 * A road the drawn route still uses says so: with no way around it, the route keeps it.
 */
const meta = {
    title: 'Components/AvoidedRoads',
    component: AvoidedRoads,
    args: {
        roads: avoidedRoads,
        picking: false,
        crossing: [],
        busy: false,
        onPick: fn(),
        onShow: fn(),
        onRemove: fn(),
    },
    argTypes: {
        roads: {
            control: 'object',
            description: 'The roads the rider avoids, oldest first.',
        },
        picking: {
            control: 'boolean',
            description: 'On while the next tap on the map avoids a road.',
        },
        crossing: {
            control: 'object',
            description: 'The ids of the roads the drawn route still uses.',
        },
        busy: {
            control: 'boolean',
            description: 'On while a road is found or let back in.',
        },
        onPick: {
            action: 'picking',
            description: 'Avoid a road, or stop waiting for the tap.',
        },
        onShow: { action: 'shown', description: 'Show, for one road.' },
        onRemove: { action: 'removed', description: 'Remove, for one road.' },
    },
    decorators: [
        (Story) => (
            <PlannerScene>
                <div className="panel open" style={{ maxWidth: 380 }}>
                    <Story />
                </div>
            </PlannerScene>
        ),
    ],
} satisfies Meta<typeof AvoidedRoads>;

export default meta;

type Story = StoryObj<typeof meta>;

/** One road avoided. Show and Remove each name it, for a screen reader. */
export const OneRoad: Story = {
    play: async ({ canvas, args, userEvent, step }) => {
        await step('Show it on the map', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Show Zandstraat on the map',
                }),
            );
            await expect(args.onShow).toHaveBeenCalledWith(avoidedRoads[0]);
        });

        await step('Let it back in', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Remove Zandstraat',
                }),
            );
            await expect(args.onRemove).toHaveBeenCalledWith(avoidedRoads[0]);
        });

        await step('Avoid another', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Avoid a road' }),
            );
            await expect(args.onPick).toHaveBeenCalledWith(true);
        });
    },
};

/** Nothing avoided yet: what it is for, in a line. */
export const None: Story = {
    args: { roads: [] },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByText(/Avoid it, and Traceway skips it on every plan/),
        ).toBeVisible();
    },
};

/** A way with no name in OpenStreetMap, beside a named one. */
export const Unnamed: Story = {
    args: { roads: [...avoidedRoads, unnamed] },
    play: async ({ canvas }) => {
        await expect(canvas.getByText('Unnamed road')).toBeVisible();
    },
};

/** Waiting for the tap: the button stays pressed and says how to stop. */
export const Picking: Story = {
    args: { picking: true },
    play: async ({ canvas, args, userEvent }) => {
        const button = canvas.getByRole('button', {
            name: 'Tap the road on the map, or tap here to stop',
        });

        await expect(button).toHaveAttribute('aria-pressed', 'true');
        await userEvent.click(button);
        await expect(args.onPick).toHaveBeenCalledWith(false);
    },
};

/** The drawn route still uses the road: there was no way around it. */
export const StillOnTheRoute: Story = {
    args: { crossing: [avoidedRoads[0]?.id ?? ''] },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByText('Still on this route: no way around it'),
        ).toBeVisible();
    },
};

/** While a road is found: Remove waits, and the button says what is happening. */
export const Finding: Story = {
    args: { busy: true },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Finding the road…' }),
        ).toBeDisabled();
        await expect(
            canvas.getByRole('button', { name: 'Remove Zandstraat' }),
        ).toBeDisabled();
    },
};
