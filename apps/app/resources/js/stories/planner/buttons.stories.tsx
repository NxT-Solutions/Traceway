import type { Meta, StoryObj } from '@storybook/react-vite';
import { Download, Pencil, Plus } from 'lucide-react';
import { expect, fn, userEvent, within } from 'storybook/test';
import { PlannerScene } from '../support/planner-scene';

type ButtonArgs = {
    /** What the button says. */
    label: string;
    disabled: boolean;
    onClick: () => void;
};

/**
 * The planner's buttons, from `planner.css`. One chartreuse `fill` for the step that moves the rider forward,
 * outlines for the rest. Press feedback is opacity .72 for 80ms; hover is `chart-dim` or `hover`. One `fill` per
 * panel. The Controls change the label and disable it; the Actions panel logs each press.
 *
 * - `fill`: the full-width forward action, 48px. "Plan a loop", "Send to device".
 * - `new`: the chart call to action in the library toolbar, 42px.
 * - `outline`: every other action, 42px. Exports and device sends.
 * - `ghostbtn`: a 36px square icon button. Always has an `aria-label`.
 * - `textbtn`: dismissive actions in a popover, in `planner-muted`.
 */
const meta = {
    title: 'Planner/Buttons',
    parameters: { layout: 'fullscreen' },
    args: { label: 'Plan a loop', disabled: false, onClick: fn() },
} satisfies Meta<ButtonArgs>;

export default meta;

type Story = StoryObj<ButtonArgs>;

/** A press reaches the handler once. */
async function pressOnce(
    canvasElement: HTMLElement,
    args: ButtonArgs,
): Promise<void> {
    await userEvent.click(
        within(canvasElement).getByRole('button', { name: args.label }),
    );
    await expect(args.onClick).toHaveBeenCalledOnce();
}

export const Fill: Story = {
    render: ({ label, disabled, onClick }) => (
        <PlannerScene>
            <div style={{ width: 352 }}>
                <button
                    className="fill"
                    type="button"
                    disabled={disabled}
                    onClick={onClick}
                >
                    {label}
                </button>
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement }) => pressOnce(canvasElement, args),
};

/** While the server draws, the forward action waits and takes no press. */
export const FillDisabled: Story = {
    ...Fill,
    args: { label: 'Planning…', disabled: true },
    play: async ({ args, canvasElement }) => {
        const button = within(canvasElement).getByRole('button', {
            name: args.label,
        });

        await expect(button).toBeDisabled();
        await userEvent.click(button, { pointerEventsCheck: 0 });
        await expect(args.onClick).not.toHaveBeenCalled();
    },
};

export const Outline: Story = {
    args: { label: 'Send to device' },
    render: ({ label, disabled, onClick }) => (
        <PlannerScene>
            <div className="btns" style={{ width: 352 }}>
                <button
                    className="outline"
                    type="button"
                    disabled={disabled}
                    onClick={onClick}
                >
                    <Download size={18} aria-hidden="true" /> {label}
                </button>
                <button className="outline" type="button" disabled={disabled}>
                    GPX file
                </button>
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement }) => pressOnce(canvasElement, args),
};

export const QuietActions: Story = {
    args: { label: 'Keep it' },
    render: ({ label, disabled, onClick }) => (
        <PlannerScene>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <button
                    className="ghostbtn"
                    type="button"
                    aria-label="Rename route"
                    disabled={disabled}
                >
                    <Pencil size={18} aria-hidden="true" />
                </button>
                <button className="textbtn" type="button" disabled={disabled}>
                    Cancel
                </button>
                <button
                    className="outline sm"
                    type="button"
                    disabled={disabled}
                    onClick={onClick}
                >
                    {label}
                </button>
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement }) => pressOnce(canvasElement, args),
};

/** A link, not a button: it opens the planner, as the library's toolbar does. */
export const NewRoute: Story = {
    args: { label: 'New route' },
    render: ({ label }) => (
        <PlannerScene library>
            <a className="new" href="/dashboard">
                <Plus size={18} aria-hidden="true" style={{ marginRight: 8 }} />
                {label}
            </a>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement }) => {
        const link = within(canvasElement).getByRole('link', {
            name: args.label,
        });

        await expect(link).toHaveAttribute('href', '/dashboard');
        await userEvent.tab();
        await expect(link).toHaveFocus();
    },
};
