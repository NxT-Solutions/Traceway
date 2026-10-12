import { useDragReorder } from '@/hooks/use-drag-reorder';
import {
    type Colour,
    type ColourSet,
    colourName,
    colourSets,
    customSession,
    type LandLeg,
    type Layout,
    layouts,
    mostLandLegs,
    presets,
    raceDistancesFor,
    raceOf,
    sessionOf,
} from '@/lib/multisport';
import { type Coordinate, metresBetween } from '@/lib/route-arrows';

export type LegSheet =
    | 'sport'
    | 'length'
    | 'surface'
    | 'elevation'
    | 'roads'
    | 'colour'
    | 'race'
    | 'session';

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
    colours,
    colourSet,
    swimTargetKm,
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
    /** The colour of each leg in order, the swim first when there is one. */
    colours: string[];
    colourSet: ColourSet;
    /** How far the swim of the chosen race is, so the athlete can draw it. */
    swimTargetKm: number | null;
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
    const legDrag = useDragReorder(
        legs.map((leg) => leg.id),
        onMove,
    );
    const active = legs[activeLeg] ?? legs[0];
    const drawingSwim = placing?.kind === 'swim';
    const session = sessionOf(legs, swim !== null);
    const sessionCopy =
        [...presets, customSession].find((item) => item.value === session)
            ?.copy ?? '';
    const races = raceDistancesFor(session);
    const race = raceOf(session, legs, swimTargetKm);

    return (
        <>
            <section aria-label="Session">
                <p className="sec-label">Session</p>
                <button
                    type="button"
                    className="row"
                    onClick={() => onOpen('session')}
                >
                    <span className="k">Type</span>
                    <span className="v">
                        {[...presets, customSession].find(
                            (item) => item.value === session,
                        )?.label ?? customSession.label}
                        <Chevron />
                    </span>
                </button>
                {races.length > 0 ? (
                    <button
                        type="button"
                        className="row"
                        onClick={() => onOpen('race')}
                    >
                        <span className="k">Race distance</span>
                        <span className="v">
                            {race === null ? 'Your own' : race.label}
                            <Chevron />
                        </span>
                    </button>
                ) : null}
                <p className="small" style={{ marginTop: 8 }}>
                    {race === null ? sessionCopy : race.copy}
                </p>
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
                            onClick={() => onLayout(option.value)}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
                <p className="small" style={{ marginTop: 8 }}>
                    {layouts.find((option) => option.value === layout)?.copy}
                </p>
                <button
                    type="button"
                    className="row"
                    onClick={() => onOpen('colour')}
                >
                    <span className="k">Colours</span>
                    <span className="v">
                        <span className="swatch-strip" aria-hidden="true">
                            {colours.map((colour, index) => (
                                <i
                                    // A plan's legs are fixed in number.
                                    key={`${colour}-${index}`}
                                    style={{ background: colour }}
                                />
                            ))}
                        </span>
                        {colourSets.find((set) => set.value === colourSet)
                            ?.label ?? 'Bold'}
                        <Chevron />
                    </span>
                </button>
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
                <div className="pts" ref={legDrag.listRef}>
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
                            <span
                                className="mark swim"
                                aria-hidden="true"
                                style={{
                                    borderColor: colours[0],
                                    color: colours[0],
                                }}
                            >
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
                                        ? swimTargetKm === null
                                            ? 'Tap the water'
                                            : `Draw ${swimTargetKm} km`
                                        : swim.length === 1
                                          ? 'Pin'
                                          : swimTargetKm === null
                                            ? `${swimKm(swim)} km`
                                            : `${swimKm(swim)} of ${swimTargetKm} km`}
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
                            data-point={item.id}
                        >
                            <span
                                className="mark leg"
                                aria-hidden="true"
                                style={{
                                    background: colours[index + offset - 1],
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
                                            : item.stretch === true
                                              ? `${item.distanceKm} km to its finish`
                                              : 'Shortest way'
                                        : `${item.distanceKm} km`}
                                </small>
                                <span className="vh">Edit this leg</span>
                            </button>
                            <button
                                type="button"
                                className="grip"
                                aria-label={`Move leg ${index + offset}. Drag, or use the arrow keys.`}
                                onPointerDown={(event) =>
                                    legDrag.startDrag(event)
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key !== 'ArrowUp' &&
                                        event.key !== 'ArrowDown'
                                    ) {
                                        return;
                                    }

                                    event.preventDefault();
                                    legDrag.move(
                                        index,
                                        event.key === 'ArrowUp'
                                            ? index - 1
                                            : index + 1,
                                    );
                                }}
                            >
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 14 14"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <circle cx="4.5" cy="2.5" r="1.3" />
                                    <circle cx="9.5" cy="2.5" r="1.3" />
                                    <circle cx="4.5" cy="7" r="1.3" />
                                    <circle cx="9.5" cy="7" r="1.3" />
                                    <circle cx="4.5" cy="11.5" r="1.3" />
                                    <circle cx="9.5" cy="11.5" r="1.3" />
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
                    ) : null}
                    <button
                        type="button"
                        className="row"
                        onClick={() => onOpen('length')}
                    >
                        <span className="k">Length</span>
                        <span className="v">
                            {layout === 'chain' && active.stretch !== true
                                ? 'Shortest way'
                                : `${active.distanceKm} km`}
                            <Chevron />
                        </span>
                    </button>
                    <button
                        type="button"
                        className="row"
                        onClick={() => onOpen('colour')}
                    >
                        <span className="k">Colour</span>
                        <span className="v">
                            <i
                                className="swatch-dot"
                                aria-hidden="true"
                                style={{
                                    background: colours[activeLeg + offset - 1],
                                }}
                            />
                            {colourName(colours[activeLeg + offset - 1] ?? '')}
                            <Chevron />
                        </span>
                    </button>
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

/**
 * Pick a colour set for the whole plan, then change any one leg to another
 * colour from that set. Every colour in a set stands apart from the others,
 * so legs that share a road can still be told apart.
 */
export function LegColourPicker({
    colourSet,
    palette,
    legs,
    onSet,
    onPick,
}: {
    colourSet: ColourSet;
    palette: readonly Colour[];
    legs: Array<{ label: string; colour: string }>;
    onSet: (value: ColourSet) => void;
    onPick: (index: number, hex: string) => void;
}) {
    const shared = new Set(
        legs
            .map((leg) => leg.colour)
            .filter((colour, index, all) => all.indexOf(colour) !== index),
    );

    return (
        <div className="colour-picker">
            <div className="choices" role="radiogroup" aria-label="Colour set">
                {colourSets.map((set) => (
                    <button
                        key={set.value}
                        type="button"
                        role="radio"
                        aria-checked={colourSet === set.value}
                        className={
                            colourSet === set.value ? 'choice on' : 'choice'
                        }
                        onClick={() => onSet(set.value)}
                    >
                        <span className="t">
                            <b>{set.label}</b>
                            <small>{set.copy}</small>
                        </span>
                        <span className="swatch-strip" aria-hidden="true">
                            {set.colours.map((colour) => (
                                <i
                                    key={colour.hex}
                                    style={{ background: colour.hex }}
                                />
                            ))}
                        </span>
                    </button>
                ))}
            </div>
            <div className="leg-colours">
                {legs.map((leg, index) => (
                    <div
                        // Legs are listed in plan order.
                        key={`${leg.label}-${index}`}
                        className="leg-colour"
                    >
                        <span className="name">
                            <b>{index + 1}</b> {leg.label}
                        </span>
                        <div
                            className="swatches"
                            role="radiogroup"
                            aria-label={`Colour for leg ${index + 1}, ${leg.label}`}
                        >
                            {palette.map((colour) => (
                                <button
                                    key={colour.hex}
                                    type="button"
                                    role="radio"
                                    aria-checked={leg.colour === colour.hex}
                                    aria-label={colour.name}
                                    title={colour.name}
                                    className="swatch"
                                    style={{ background: colour.hex }}
                                    onClick={() => onPick(index, colour.hex)}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
            {shared.size > 0 ? (
                <p className="small" role="status">
                    Two legs share a colour. Where they overlap, they will look
                    like one line.
                </p>
            ) : (
                <p className="small">
                    Choosing a set gives every leg its own colour again.
                </p>
            )}
        </div>
    );
}
