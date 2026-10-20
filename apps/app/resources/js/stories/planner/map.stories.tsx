import type { Meta, StoryObj } from '@storybook/react-vite';
import { LocateFixed, Minus, Plus } from 'lucide-react';
import { expect, fn, userEvent, within } from 'storybook/test';
import { MapThumb, PlannerScene } from '../support/planner-scene';

type MapArgs = {
    /** The hint's lead-in, before the move it names. */
    hint: string;
    /** The move the hint names, in bold. */
    move: string;
    /** What the toast says after an action. */
    toast: string;
    onZoomIn: () => void;
    onZoomOut: () => void;
    onLocate: () => void;
};

/**
 * What floats on the map, from `planner.css`: the controls at the bottom right, the hint that names the next move,
 * and the toast after an action. Each sits on `panel` with `shadow-panel`, except the toast, which inverts to `text`.
 * The route is drawn in `route` over a `halo`, never in the marker colour; loops not picked are `loser`. The Controls
 * change the copy; the Actions panel logs the map buttons.
 */
const meta = {
    title: 'Planner/Map',
    parameters: { layout: 'fullscreen' },
    args: {
        hint: 'Tap the map to',
        move: 'drop a pin',
        toast: 'Sent to your Edge 540',
        onZoomIn: fn(),
        onZoomOut: fn(),
        onLocate: fn(),
    },
} satisfies Meta<MapArgs>;

export default meta;

type Story = StoryObj<MapArgs>;

/** Zoom and locate, each a 44px target with its own name. */
export const Controls: Story = {
    render: ({ onZoomIn, onZoomOut, onLocate }) => (
        <PlannerScene>
            <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                <div className="ctl">
                    <button
                        type="button"
                        aria-label="Zoom in"
                        onClick={onZoomIn}
                    >
                        <Plus size={18} aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        aria-label="Zoom out"
                        onClick={onZoomOut}
                    >
                        <Minus size={18} aria-hidden="true" />
                    </button>
                </div>
                <div className="ctl">
                    <button
                        type="button"
                        aria-label="Show my location"
                        onClick={onLocate}
                    >
                        <LocateFixed size={18} aria-hidden="true" />
                    </button>
                </div>
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement, step }) => {
        const canvas = within(canvasElement);

        await step('Zoom in twice and out once', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Zoom in' }),
            );
            await userEvent.click(
                canvas.getByRole('button', { name: 'Zoom in' }),
            );
            await userEvent.click(
                canvas.getByRole('button', { name: 'Zoom out' }),
            );
            await expect(args.onZoomIn).toHaveBeenCalledTimes(2);
            await expect(args.onZoomOut).toHaveBeenCalledOnce();
        });

        await step('Tab from Zoom out reaches Show my location', async () => {
            await userEvent.tab();
            await expect(
                canvas.getByRole('button', { name: 'Show my location' }),
            ).toHaveFocus();
            await userEvent.keyboard('{Enter}');
            await expect(args.onLocate).toHaveBeenCalledOnce();
        });
    },
};

export const Hint: Story = {
    render: ({ hint, move }) => (
        <PlannerScene>
            <p
                className="maphint"
                style={{
                    position: 'relative',
                    left: 0,
                    top: 0,
                    transform: 'none',
                }}
            >
                {hint} <b>{move}</b>
            </p>
        </PlannerScene>
    ),
};

export const Toast: Story = {
    render: ({ toast }) => (
        <PlannerScene>
            <div
                className="toast"
                role="status"
                style={{
                    position: 'relative',
                    left: 0,
                    bottom: 0,
                    transform: 'none',
                    display: 'inline-block',
                }}
            >
                {toast}
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('status'),
        ).toHaveTextContent(args.toast);
    },
};

/** The map palette and the route line, as the planner draws them. */
export const RouteOnTheMap: Story = {
    render: () => (
        <PlannerScene>
            <div
                style={{
                    width: 480,
                    maxWidth: '100%',
                    aspectRatio: '16 / 10',
                    borderRadius: 12,
                    overflow: 'hidden',
                }}
            >
                <MapThumb />
            </div>
        </PlannerScene>
    ),
};
