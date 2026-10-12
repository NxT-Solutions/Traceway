import {
    type LandLeg,
    type Layout,
    layouts,
    legPaint,
    mostLandLegs,
    presets,
} from '@/lib/multisport';
import { type Coordinate, metresBetween } from '@/lib/route-arrows';

export type LegSheet = 'sport' | 'length' | 'surface' | 'elevation' | 'roads';

export type Placing = { kind: 'swim' } | { kind: 'finish'; leg: number } | null;

type Labels = {
    sport: (value: string) => string;
    surface: (value: string) => string;
    elevation: (value: string) => string;
    roads: (value: string) => string;
};

function Chevron() {
    return (
        <svg
            width="16"
            height="16"
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M6 3 L11 8 L6 13"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity="0.7"
            />
        </svg>
    );
}

function Cross() {
    return (
        <svg
            width="14"
            height="14"
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M4 4 L12 12 M12 4 L4 12"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
        </svg>
    );
}

function swimKm(points: Coordinate[]): string {
    let metres = 0;

    for (let index = 1; index < points.length; index++) {
        const from = points[index - 1];
        const to = points[index];

        if (from !== undefined && to !== undefined) {
            metres += metresBetween(from, to);
        }
    }

    return (metres / 1000).toFixed(1);
}

/**
 * The legs of a brick, duathlon, or triathlon, in order, and the settings
 * of the leg being edited. The planner sheets edit the active leg.
 */
export function MultisportLegs({
    legs,
    layout,
    swim,
    activeLeg,
    placing,
    labels,
    onPreset,
    onLayout,
    onSwim,
    onDrawSwim,
    onSelect,
    onOpen,
    onAdd,
    onRemove,
    onMove,
    onPlaceFinish,
}: {
    legs: LandLeg[];
    layout: Layout;
    swim: Coordinate[] | null;
    activeLeg: number;
    placing: Placing;
    labels: Labels;
    onPreset: (value: (typeof presets)[number]['value']) => void;
    onLayout: (value: Layout) => void;
    onSwim: (enabled: boolean) => void;
    onDrawSwim: () => void;
    onSelect: (index: number) => void;
    onOpen: (sheet: LegSheet) => void;
    onAdd: () => void;
    onRemove: (index: number) => void;
    onMove: (from: number, to: number) => void;
    onPlaceFinish: (index: number) => void;
}) {
    const offset = swim === null ? 1 : 2;
    const active = legs[activeLeg] ?? legs[0];
    const drawingSwim = placing?.kind === 'swim';

    return (
        <>
            <section aria-label="Session">
                <p className="sec-label">Session</p>
                <div className="seg" role="group" aria-label="Session preset">
                    {presets.map((preset) => (
                        <button
                            key={preset.value}
                            type="button"
                            title={preset.copy}
                            onClick={() => onPreset(preset.value)}
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>
                <div
                    className="seg"
                    role="radiogroup"
                    aria-label="How the legs meet"
                    style={{ marginTop: 8 }}
                >
                    {layouts.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            role="radio"
                            aria-checked={layout === option.value}
                            aria-pressed={layout === option.value}
                            title={option.copy}
                            onClick={() => onLayout(option.value)}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
                <p className="small" style={{ marginTop: 8 }}>
                    {layouts.find((option) => option.value === layout)?.copy}
                </p>
            </section>
            <section className="legs" aria-label="Legs">
                <div className="flex items-baseline justify-between gap-3">
                    <p className="sec-label">Legs</p>
                    <p className="small">
                        {placing?.kind === 'finish'
                            ? `Tap where leg ${placing.leg + offset} ends`
                            : drawingSwim
                              ? 'Tap the water to draw the swim'
                              : 'Tap the map to move the transition'}
                    </p>
                </div>
                <div className="pts">
                    <div className="pt">
                        <span className="mark start" aria-hidden="true">
                            <span />
                        </span>
                        <div className="field">
                            <span>Transition</span>
                            <small>T</small>
                        </div>
                    </div>
                    {swim === null ? null : (
                        <div className={drawingSwim ? 'pt on' : 'pt'}>
                            <span className="mark swim" aria-hidden="true">
                                1
                            </span>
                            <button
                                type="button"
                                className="field"
                                aria-pressed={drawingSwim}
                                onClick={onDrawSwim}
                            >
                                <span>Swim</span>
                                <small className="tabular-nums">
                                    {swim.length === 0
                                        ? 'Tap the water'
                                        : swim.length === 1
                                          ? 'Pin'
                                          : `${swimKm(swim)} km`}
                                </small>
                                <span className="vh">
                                    {drawingSwim
                                        ? 'Stop drawing the swim'
                                        : 'Draw the swim on the map'}
                                </span>
                            </button>
                            <button
                                type="button"
                                className="ghostbtn"
                                aria-label="Remove the swim"
                                onClick={() => onSwim(false)}
                            >
                                <Cross />
                            </button>
                        </div>
                    )}
                    {legs.map((item, index) => (
                        <div
                            key={item.id}
                            className={index === activeLeg ? 'pt on' : 'pt'}
                        >
                            <span
                                className="mark leg"
                                aria-hidden="true"
                                style={{
                                    background: legPaint(item.sport).line,
                                }}
                            >
                                {index + offset}
                            </span>
                            <button
                                type="button"
                                className="field"
                                aria-pressed={index === activeLeg}
                                onClick={() => onSelect(index)}
                            >
                                <span>{labels.sport(item.sport)}</span>
                                <small className="tabular-nums">
                                    {layout === 'chain'
                                        ? item.finish === null
                                            ? 'No finish yet'
                                            : 'To its finish'
                                        : `${item.distanceKm} km`}
                                </small>
                                <span className="vh">Edit this leg</span>
                            </button>
                            <button
                                type="button"
                                className="grip"
                                aria-label={`Move leg ${index + offset}. Use the arrow keys.`}
                                onKeyDown={(event) => {
                                    if (
                                        event.key !== 'ArrowUp' &&
                                        event.key !== 'ArrowDown'
                                    ) {
                                        return;
                                    }

                                    event.preventDefault();
                                    onMove(
                                        index,
                                        event.key === 'ArrowUp'
                                            ? index - 1
                                            : index + 1,
                                    );
                                }}
                                onClick={() =>
                                    onMove(
                                        index,
                                        index === 0 ? index + 1 : index - 1,
                                    )
                                }
                            >
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M5 6 L8 3 L11 6 M5 10 L8 13 L11 10"
                                        stroke="currentColor"
                                        strokeWidth="1.6"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </button>
                            <button
                                type="button"
                                className="ghostbtn"
                                aria-label={`Remove leg ${index + offset}`}
                                disabled={legs.length < 2}
                                onClick={() => onRemove(index)}
                            >
                                <Cross />
                            </button>
                        </div>
                    ))}
                    {legs.length < mostLandLegs ? (
                        <button
                            type="button"
                            className="addrow"
                            onClick={onAdd}
                        >
                            <span className="mark add" aria-hidden="true">
                                +
                            </span>
                            <span>Add a leg</span>
                        </button>
                    ) : null}
                    {swim === null ? (
                        <button
                            type="button"
                            className="addrow"
                            onClick={() => onSwim(true)}
                        >
                            <span className="mark add" aria-hidden="true">
                                +
                            </span>
                            <span>Add a swim</span>
                        </button>
                    ) : null}
                </div>
            </section>
            {active === undefined ? null : (
                <section aria-label={`Leg ${activeLeg + offset}`}>
                    <p className="sec-label">{`Leg ${activeLeg + offset}`}</p>
                    <button
                        type="button"
                        className="row"
                        onClick={() => onOpen('sport')}
                    >
                        <span className="k">Sport</span>
                        <span className="v">
                            {labels.sport(active.sport)}
                            <Chevron />
                        </span>
                    </button>
                    {layout === 'chain' ? (
                        <button
                            type="button"
                            className="row"
                            onClick={() => onPlaceFinish(activeLeg)}
                        >
                            <span className="k">Finish</span>
                            <span className="v">
                                {placing?.kind === 'finish' &&
                                placing.leg === activeLeg ? (
                                    'Tap the map'
                                ) : active.finish === null ? (
                                    'None'
                                ) : (
                                    <>
                                        <span className="dot" />
                                        Set
                                    </>
                                )}
                                <Chevron />
                            </span>
                        </button>
                    ) : (
                        <button
                            type="button"
                            className="row"
                            onClick={() => onOpen('length')}
                        >
                            <span className="k">Length</span>
                            <span className="v">
                                {active.distanceKm} km
                                <Chevron />
                            </span>
                        </button>
                    )}
                    <button
                        type="button"
                        className="row"
                        onClick={() => onOpen('surface')}
                    >
                        <span className="k">Surface</span>
                        <span className="v">
                            {labels.surface(active.surface)}
                            <Chevron />
                        </span>
                    </button>
                    <button
                        type="button"
                        className="row"
                        onClick={() => onOpen('elevation')}
                    >
                        <span className="k">Hills</span>
                        <span className="v">
                            {labels.elevation(active.elevation)}
                            <Chevron />
                        </span>
                    </button>
                    <button
                        type="button"
                        className="row"
                        onClick={() => onOpen('roads')}
                    >
                        <span className="k">Roads</span>
                        <span className="v">
                            {labels.roads(active.roads)}
                            <Chevron />
                        </span>
                    </button>
                </section>
            )}
        </>
    );
}
