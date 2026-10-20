import type { Meta, StoryObj } from '@storybook/react-vite';
import { Bike, ChevronLeft, ChevronRight, Pencil } from 'lucide-react';
import { type KeyboardEvent, useState } from 'react';
import { expect, fn, userEvent, within } from 'storybook/test';
import { PlannerScene } from '../support/planner-scene';

const rowKeys = ['Start', 'Distance', 'Pass through', 'Surface'] as const;
type RowKey = (typeof rowKeys)[number];

type PanelArgs = {
    /** The route's name in the panel head. */
    name: string;
    /** The sport chosen in the head's segmented control. */
    sport: 'Run' | 'Walk' | 'Ride';
    start: string;
    distanceKm: number;
    /** The place the loop passes, or empty for none. */
    via: string;
    surface: 'Paved' | 'Any' | 'Dirt';
    /** The row whose popover is open, or None. */
    openRow: RowKey | 'None';
    /** A loop came back: the result shows, and the forward action is Send to device. */
    planned: boolean;
    /** How many loops came back, for the stepper. */
    loopCount: number;
    ascentMetres: number;
    onPlan: () => void;
    onSend: () => void;
    onRename: () => void;
    onOpenRow: (row: RowKey | null) => void;
    onSport: (sport: string) => void;
    onLoop: (loop: number) => void;
};

/**
 * One choice from two to six, as radios in a hairline frame. Only the chosen radio is in the tab order; the arrow
 * keys move the choice and the focus along, wrapping at the ends, and Home and End jump to them.
 */
function Segmented({
    label,
    options,
    initial,
    onChange,
}: {
    label: string;
    options: readonly string[];
    initial: string;
    onChange: (value: string) => void;
}) {
    const [value, setValue] = useState(initial);

    function choose(next: string, group?: HTMLElement): void {
        setValue(next);
        onChange(next);
        group
            ?.querySelectorAll<HTMLButtonElement>('[role="radio"]')
            [options.indexOf(next)]?.focus();
    }

    function onKeyDown(event: KeyboardEvent<HTMLDivElement>): void {
        const index = options.indexOf(value);
        const last = options.length - 1;
        const target =
            event.key === 'ArrowRight' || event.key === 'ArrowDown'
                ? index === last
                    ? 0
                    : index + 1
                : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
                  ? index === 0
                      ? last
                      : index - 1
                  : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? last
                      : null;
        const next = target === null ? undefined : options[target];

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        choose(next, event.currentTarget);
    }

    return (
        <div
            className="seg"
            role="radiogroup"
            aria-label={label}
            onKeyDown={onKeyDown}
        >
            {options.map((option) => (
                <button
                    key={option}
                    type="button"
                    role="radio"
                    aria-checked={option === value}
                    tabIndex={option === value ? 0 : -1}
                    onClick={() => choose(option)}
                >
                    {option}
                </button>
            ))}
        </div>
    );
}

function Rows({
    values,
    open,
    onOpen,
}: {
    values: Record<RowKey, string>;
    open: RowKey | 'None';
    onOpen: (row: RowKey | null) => void;
}) {
    const [expanded, setExpanded] = useState<RowKey | null>(
        open === 'None' ? null : open,
    );

    return (
        <div>
            <p className="sec-label">Your loop</p>
            {rowKeys.map((key) => (
                <button
                    key={key}
                    className="row"
                    type="button"
                    aria-expanded={expanded === key}
                    onClick={() => {
                        const next = expanded === key ? null : key;
                        setExpanded(next);
                        onOpen(next);
                    }}
                >
                    <span className="k">{key}</span>
                    <span className="v">
                        {key === 'Pass through' && values[key] !== 'None' ? (
                            <i className="dot" aria-hidden="true" />
                        ) : null}
                        {values[key]}
                        <ChevronRight size={16} aria-hidden="true" />
                    </span>
                </button>
            ))}
        </div>
    );
}

/** The distance large, the climb beside it, and a stepper through the loops found, from the first to the last. */
function Result({
    distanceKm,
    ascentMetres,
    loopCount,
    onLoop,
}: Pick<PanelArgs, 'distanceKm' | 'ascentMetres' | 'loopCount' | 'onLoop'>) {
    const count = Math.max(1, Math.round(loopCount));
    const [loop, setLoop] = useState(1);
    const go = (next: number) => {
        setLoop(next);
        onLoop(next);
    };

    return (
        <div className="result">
            <div className="top">
                <div className="big">
                    <b>{distanceKm.toFixed(1)} km</b>
                    <span>{ascentMetres} m up</span>
                </div>
                <div className="stepper">
                    <button
                        type="button"
                        aria-label="Previous loop"
                        disabled={loop <= 1}
                        onClick={() => go(loop - 1)}
                    >
                        <ChevronLeft size={16} aria-hidden="true" />
                    </button>
                    <span>
                        <i style={{ background: 'var(--chart)' }} />
                        {`Loop ${loop} of ${count}`}
                    </span>
                    <button
                        type="button"
                        aria-label="Next loop"
                        disabled={loop >= count}
                        onClick={() => go(loop + 1)}
                    >
                        <ChevronRight size={16} aria-hidden="true" />
                    </button>
                </div>
            </div>
            <div className="legend">
                <span>
                    <i />
                    Your loop
                </span>
                <span>
                    <i className="dotted" />
                    Other loops
                </span>
            </div>
        </div>
    );
}

function Panel(args: PanelArgs) {
    return (
        // Open, so a window under 1024px shows it whole rather than as the
        // phone's closed bottom sheet.
        <div
            className="panel open"
            style={{ position: 'relative', left: 0, top: 0, height: 'auto' }}
        >
            <div className="phead">
                <div className="title-row">
                    <span className="icon-box">
                        <Bike size={20} aria-hidden="true" />
                    </span>
                    <h2 className="route-title">{args.name}</h2>
                    <button
                        className="ghostbtn"
                        type="button"
                        aria-label="Rename route"
                        onClick={args.onRename}
                    >
                        <Pencil size={18} aria-hidden="true" />
                    </button>
                </div>
                <Segmented
                    label="Sport"
                    options={['Run', 'Walk', 'Ride']}
                    initial={args.sport}
                    onChange={args.onSport}
                />
            </div>
            <div className="pbody">
                <Rows
                    values={{
                        Start: args.start,
                        Distance: `${args.distanceKm} km`,
                        'Pass through': args.via.trim() || 'None',
                        Surface: args.surface,
                    }}
                    open={args.openRow}
                    onOpen={args.onOpenRow}
                />
                {args.planned ? <Result {...args} /> : null}
                <button
                    className="fill"
                    type="button"
                    onClick={args.planned ? args.onSend : args.onPlan}
                >
                    {args.planned ? 'Send to device' : 'Plan a loop'}
                </button>
            </div>
        </div>
    );
}

/** Remounts the panel when a Control changes, so the stepper and the open row start again from the args. */
function Scene(args: PanelArgs) {
    return (
        <PlannerScene>
            <Panel key={JSON.stringify(args)} {...args} />
        </PlannerScene>
    );
}

/**
 * The floating readout on the left of the map, from `planner.css`: route name and sport on top, settings and the
 * result below, one chart action at the foot. 400px wide, `panel` on `panel-b` at `radius-panel` with
 * `shadow-panel`. Under 768px it becomes a bottom sheet. The map is the page; the panel never covers more than the
 * left 424px on a desktop. The Controls set what it reads; the Actions panel logs what a rider presses.
 */
const meta = {
    title: 'Planner/Panel',
    component: Scene,
    parameters: { layout: 'fullscreen' },
    args: {
        name: 'Morning loop',
        sport: 'Ride',
        start: 'Westerlo',
        distanceKm: 40,
        via: 'Bergom',
        surface: 'Paved',
        openRow: 'None',
        planned: false,
        loopCount: 3,
        ascentMetres: 180,
        onPlan: fn(),
        onSend: fn(),
        onRename: fn(),
        onOpenRow: fn(),
        onSport: fn(),
        onLoop: fn(),
    },
    argTypes: {
        sport: { control: 'inline-radio', options: ['Run', 'Walk', 'Ride'] },
        surface: { control: 'inline-radio', options: ['Paved', 'Any', 'Dirt'] },
        openRow: {
            name: 'open row',
            control: 'select',
            options: ['None', ...rowKeys],
        },
        distanceKm: {
            name: 'distance (km)',
            control: { type: 'range', min: 5, max: 200, step: 1 },
        },
        loopCount: {
            name: 'loops found',
            control: { type: 'range', min: 1, max: 3, step: 1 },
        },
        ascentMetres: { name: 'climb (m)', control: { type: 'number' } },
    },
} satisfies Meta<typeof Scene>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Before planning, the forward action is Plan a loop. */
export const BeforePlanning: Story = {
    play: async ({ args, canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(
            canvas.getByRole('button', { name: 'Plan a loop' }),
        );
        await expect(args.onPlan).toHaveBeenCalledOnce();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Rename route' }),
        );
        await expect(args.onRename).toHaveBeenCalledOnce();
    },
};

/** A loop came back: the result and its stepper show, and the forward action is Send to device. */
export const LoopFound: Story = {
    args: { name: 'Morning loop', distanceKm: 41.6, planned: true },
    play: async ({ args, canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(
            canvas.getByRole('button', { name: 'Next loop' }),
        );
        await expect(canvas.getByText('Loop 2 of 3')).toBeVisible();
        await expect(args.onLoop).toHaveBeenLastCalledWith(2);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Send to device' }),
        );
        await expect(args.onSend).toHaveBeenCalledOnce();
    },
};

/** The planner's settings as a ruled list. A row that opens a popover marks its value with the marker while open: `chart` text on Ink, ink text underlined in `chart` on Paper, where chartreuse text would not read. */
export const ReadoutRows: Story = {
    args: { openRow: 'Distance' },
    render: (args) => (
        <PlannerScene>
            <div style={{ width: 360 }}>
                <Rows
                    key={args.openRow}
                    values={{
                        Start: args.start,
                        Distance: `${args.distanceKm} km`,
                        'Pass through': args.via.trim() || 'None',
                        Surface: args.surface,
                    }}
                    open={args.openRow}
                    onOpen={args.onOpenRow}
                />
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement, step }) => {
        const canvas = within(canvasElement);
        const row = (name: RegExp) => canvas.getByRole('button', { name });

        await step('Distance is open', async () => {
            await expect(row(/^Distance/)).toHaveAttribute(
                'aria-expanded',
                'true',
            );
        });

        await step('Opening Surface closes Distance', async () => {
            await userEvent.click(row(/^Surface/));
            await expect(row(/^Surface/)).toHaveAttribute(
                'aria-expanded',
                'true',
            );
            await expect(row(/^Distance/)).toHaveAttribute(
                'aria-expanded',
                'false',
            );
            await expect(args.onOpenRow).toHaveBeenLastCalledWith('Surface');
        });

        await step('Pressing it again closes it', async () => {
            await userEvent.click(row(/^Surface/));
            await expect(row(/^Surface/)).toHaveAttribute(
                'aria-expanded',
                'false',
            );
            await expect(args.onOpenRow).toHaveBeenLastCalledWith(null);
        });
    },
};

/** The answer: the distance large in `stat`, the climbing beside it, and a stepper through the loops found. */
export const RouteResult: Story = {
    args: { distanceKm: 41.6, planned: true },
    render: (args) => (
        <PlannerScene>
            <div style={{ width: 360 }}>
                <Result key={args.loopCount} {...args} />
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement, step }) => {
        const canvas = within(canvasElement);
        const previous = canvas.getByRole('button', { name: 'Previous loop' });
        const next = canvas.getByRole('button', { name: 'Next loop' });

        await step('The first loop: nothing before it', async () => {
            await expect(canvas.getByText('Loop 1 of 3')).toBeVisible();
            await expect(previous).toBeDisabled();
        });

        await step('Step to the last loop', async () => {
            await userEvent.click(next);
            await userEvent.click(next);
            await expect(canvas.getByText('Loop 3 of 3')).toBeVisible();
            await expect(next).toBeDisabled();
            await expect(args.onLoop).toHaveBeenLastCalledWith(3);
        });

        await step('And one back', async () => {
            await userEvent.click(previous);
            await expect(canvas.getByText('Loop 2 of 3')).toBeVisible();
            await expect(args.onLoop).toHaveBeenLastCalledWith(2);
        });
    },
};

const sportsInFull: readonly string[] = [
    'Run',
    'Trail',
    'Road',
    'Gravel',
    'MTB',
];

/**
 * Equal options in a hairline frame, for one choice from two to six. Foot and bike get equal weight. Tab reaches
 * the chosen option; the arrow keys move the choice. The `sport` Control picks the start (Ride starts on Road).
 */
export const SegmentedControl: Story = {
    args: { sport: 'Ride' },
    render: (args) => (
        <PlannerScene>
            <div style={{ display: 'grid', gap: 12, width: 420 }}>
                <Segmented
                    key={args.sport}
                    label="Sport"
                    options={sportsInFull}
                    initial={
                        sportsInFull.includes(args.sport) ? args.sport : 'Road'
                    }
                    onChange={args.onSport}
                />
                <Segmented
                    label="Surface"
                    options={['Paved', 'Dirt']}
                    initial="Paved"
                    onChange={fn()}
                />
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement, step }) => {
        const canvas = within(canvasElement);
        const sport = within(canvas.getByRole('radiogroup', { name: 'Sport' }));
        const radio = (name: string) => sport.getByRole('radio', { name });

        await step('Tab reaches the chosen sport', async () => {
            await userEvent.tab();
            await expect(radio('Road')).toHaveFocus();
            await expect(radio('Road')).toBeChecked();
        });

        await step('Arrow right chooses the next one', async () => {
            await userEvent.keyboard('{ArrowRight}');
            await expect(radio('Gravel')).toBeChecked();
            await expect(radio('Gravel')).toHaveFocus();
            await expect(radio('Road')).not.toBeChecked();
            await expect(args.onSport).toHaveBeenLastCalledWith('Gravel');
        });

        await step('The arrows wrap at the ends', async () => {
            await userEvent.keyboard('{ArrowRight}{ArrowRight}');
            await expect(radio('Run')).toBeChecked();
            await userEvent.keyboard('{ArrowLeft}');
            await expect(radio('MTB')).toBeChecked();
        });

        await step('Home and End jump to the ends', async () => {
            await userEvent.keyboard('{Home}');
            await expect(radio('Run')).toHaveFocus();
            await userEvent.keyboard('{End}');
            await expect(radio('MTB')).toHaveFocus();
        });

        await step('Tab leaves the group for the next one', async () => {
            await userEvent.tab();
            await expect(
                within(
                    canvas.getByRole('radiogroup', { name: 'Surface' }),
                ).getByRole('radio', { name: 'Paved' }),
            ).toHaveFocus();
        });
    },
};
