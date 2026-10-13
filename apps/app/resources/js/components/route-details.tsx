import {
    ArrowDownLeft,
    ArrowDownRight,
    ArrowUpLeft,
    ArrowUpRight,
    Coffee,
    CornerUpLeft,
    CornerUpRight,
    Droplet,
    type LucideIcon,
    RotateCw,
    Toilet,
    Undo2,
} from 'lucide-react';
import { type KeyboardEvent, type PointerEvent, useId, useState } from 'react';
import {
    type Coordinate,
    type Cue,
    elevationAt,
    gradeBands,
    gradeColour,
    grades,
    type RouteDetails,
    type StopKind,
    stopColours,
    stopLabels,
    surfaceColours,
    surfaceLabels,
    type Turn,
    turnLabels,
} from '@/lib/route-details';

const chartWidth = 1000;
const chartHeight = 200;

function km(metres: number): string {
    if (metres < 1000 && metres > 0) {
        return `${Math.max(10, Math.round(metres / 10) * 10)} m`;
    }

    return `${(metres / 1000).toFixed(metres < 10_000 ? 1 : 0)} km`;
}

/**
 * The height of the ground along the route, filled by steepness. Moving
 * over it, or the arrow keys, reports the distance under the pointer, so
 * the map can mark the same place.
 */
export function RouteProfile({
    details,
    onHover,
    steepness,
    onSteepness,
}: {
    details: RouteDetails;
    onHover?: (metres: number | null) => void;
    steepness?: boolean;
    onSteepness?: (on: boolean) => void;
}) {
    const gradientId = useId();
    const [hover, setHover] = useState<number | null>(null);
    const profile = details.profile;
    const total = profile[profile.length - 1]?.[0] ?? 0;

    if (profile.length < 2 || total <= 0) {
        return null;
    }

    const heights = profile.map(([, height]) => height);
    const lowest = Math.min(...heights);
    const highest = Math.max(...heights);
    // A flat route still gets some room, so ten metres of rise is not a cliff.
    const span = Math.max(40, highest - lowest);
    const floor = lowest - span * 0.1;
    const x = (metres: number) => (metres / total) * chartWidth;
    const y = (height: number) =>
        chartHeight - ((height - floor) / (span * 1.2)) * chartHeight;
    const line = profile
        .map(
            ([metres, height], index) =>
                `${index === 0 ? 'M' : 'L'}${x(metres).toFixed(1)},${y(height).toFixed(1)}`,
        )
        .join('');
    const area = `${line}L${chartWidth},${chartHeight}L0,${chartHeight}Z`;
    const stretches = grades(profile);
    const hoverHeight = hover === null ? null : elevationAt(profile, hover);
    const hoverGrade =
        hover === null
            ? null
            : (stretches.find(
                  (stretch) => hover >= stretch.from && hover <= stretch.to,
              )?.grade ?? null);

    const report = (metres: number | null) => {
        setHover(metres);
        onHover?.(metres);
    };

    const pointer = (event: PointerEvent<HTMLDivElement>) => {
        const box = event.currentTarget.getBoundingClientRect();
        const share = Math.min(
            1,
            Math.max(0, (event.clientX - box.left) / box.width),
        );
        report(share * total);
    };

    const key = (event: KeyboardEvent<HTMLDivElement>) => {
        const step = total / 50;

        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            event.preventDefault();
            const from = hover ?? (event.key === 'ArrowRight' ? -step : total);
            report(
                Math.min(
                    total,
                    Math.max(
                        0,
                        from + (event.key === 'ArrowRight' ? step : -step),
                    ),
                ),
            );
        } else if (event.key === 'Escape') {
            report(null);
        }
    };

    return (
        <div className="tw-details-profile">
            <div className="tw-details-head">
                <p>
                    <b>{details.ascent_metres} m</b> up ·{' '}
                    <b>{details.descent_metres} m</b> down · highest{' '}
                    <b>{Math.round(highest)} m</b>
                </p>
                {onSteepness ? (
                    <label className="tw-details-toggle">
                        <input
                            type="checkbox"
                            checked={steepness === true}
                            onChange={(event) =>
                                onSteepness(event.target.checked)
                            }
                        />
                        Steepness on map
                    </label>
                ) : null}
            </div>
            <div
                className="tw-details-chart"
                role="slider"
                tabIndex={0}
                aria-label="Height along the route"
                aria-valuemin={0}
                aria-valuemax={Math.round(total)}
                aria-valuenow={Math.round(hover ?? 0)}
                aria-valuetext={
                    hover === null || hoverHeight === null
                        ? `${km(total)}, climbing ${details.ascent_metres} metres`
                        : `${km(hover)}, ${Math.round(hoverHeight)} metres high`
                }
                onPointerMove={pointer}
                onPointerDown={pointer}
                onPointerLeave={() => report(null)}
                onKeyDown={key}
                onBlur={() => report(null)}
            >
                <svg
                    viewBox={`0 0 ${chartWidth} ${chartHeight}`}
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <defs>
                        <linearGradient
                            id={gradientId}
                            x1="0"
                            x2={chartWidth}
                            y1="0"
                            y2="0"
                            gradientUnits="userSpaceOnUse"
                        >
                            {stretches.flatMap((stretch) => [
                                <stop
                                    key={`${stretch.from}-a`}
                                    offset={stretch.from / total}
                                    stopColor={gradeColour(stretch.grade)}
                                />,
                                <stop
                                    key={`${stretch.from}-b`}
                                    offset={stretch.to / total}
                                    stopColor={gradeColour(stretch.grade)}
                                />,
                            ])}
                        </linearGradient>
                    </defs>
                    <path
                        d={area}
                        fill={`url(#${CSS.escape(gradientId)})`}
                        opacity="0.85"
                    />
                    <path
                        d={line}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        vectorEffect="non-scaling-stroke"
                    />
                    {hover === null ? null : (
                        <line
                            x1={x(hover)}
                            x2={x(hover)}
                            y1="0"
                            y2={chartHeight}
                            stroke="currentColor"
                            strokeWidth="1"
                            vectorEffect="non-scaling-stroke"
                        />
                    )}
                </svg>
                {hover !== null && hoverHeight !== null ? (
                    <span
                        className="tw-details-tip"
                        style={{
                            left: `${Math.min(80, Math.max(0, (hover / total) * 100 - 10))}%`,
                        }}
                    >
                        {km(hover)} · {Math.round(hoverHeight)} m
                        {hoverGrade === null
                            ? ''
                            : ` · ${hoverGrade > 0 ? '+' : ''}${hoverGrade.toFixed(0)}%`}
                    </span>
                ) : null}
            </div>
            <div className="tw-details-axis" aria-hidden="true">
                <span>0 km</span>
                <span>{km(total)}</span>
            </div>
            {steepness ? (
                <ul className="tw-details-legend" aria-label="Steepness">
                    {gradeBands.map((band) => (
                        <li key={band.label}>
                            <i style={{ background: band.colour }} />
                            {band.label}
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

/**
 * What the route runs on, as one bar and a key in kilometres.
 */
export function SurfaceBar({ details }: { details: RouteDetails }) {
    const total = details.surfaces.reduce(
        (sum, share) => sum + share.metres,
        0,
    );

    if (total <= 0) {
        return null;
    }

    return (
        <div className="tw-details-surface">
            <div className="tw-details-bar" aria-hidden="true">
                {details.surfaces.map((share) => (
                    <i
                        key={share.surface}
                        style={{
                            width: `${(share.metres / total) * 100}%`,
                            background: surfaceColours[share.surface],
                        }}
                    />
                ))}
            </div>
            <ul className="tw-details-legend" aria-label="Surfaces">
                {details.surfaces.map((share) => (
                    <li key={share.surface}>
                        <i
                            style={{
                                background: surfaceColours[share.surface],
                            }}
                        />
                        {surfaceLabels[share.surface]} {km(share.metres)}
                    </li>
                ))}
            </ul>
        </div>
    );
}

const turnIcons: Record<Turn, LucideIcon> = {
    left: CornerUpLeft,
    right: CornerUpRight,
    slight_left: ArrowUpLeft,
    slight_right: ArrowUpRight,
    sharp_left: ArrowDownLeft,
    sharp_right: ArrowDownRight,
    u_turn: Undo2,
    straight: RotateCw,
};

const stopIcons: Record<StopKind, LucideIcon> = {
    water: Droplet,
    toilet: Toilet,
    cafe: Coffee,
};

function cueText(cue: Cue): string {
    if (cue.street === null) {
        return turnLabels[cue.turn];
    }

    return cue.turn === 'straight'
        ? `${turnLabels[cue.turn]}, ${cue.street.toLowerCase()}`
        : `${turnLabels[cue.turn]} onto ${cue.street}`;
}

/**
 * The turns and the stops in order along the route. Pointing at one marks
 * it on the map.
 */
export function RouteCues({
    details,
    onFocusPoint,
}: {
    details: RouteDetails;
    onFocusPoint?: (point: Coordinate | null) => void;
}) {
    const focus = (point: Coordinate) => ({
        onPointerEnter: () => onFocusPoint?.(point),
        onPointerLeave: () => onFocusPoint?.(null),
        onFocus: () => onFocusPoint?.(point),
        onBlur: () => onFocusPoint?.(null),
    });

    return (
        <div className="tw-details-lists">
            {details.stops.length > 0 ? (
                <details>
                    <summary>Stops on the way ({details.stops.length})</summary>
                    <ol>
                        {details.stops.map((stop) => {
                            const Icon = stopIcons[stop.kind];

                            return (
                                <li
                                    key={`${stop.kind}-${stop.distance_metres}`}
                                >
                                    <button
                                        type="button"
                                        {...focus([
                                            stop.longitude,
                                            stop.latitude,
                                        ])}
                                    >
                                        <Icon
                                            size={16}
                                            aria-hidden="true"
                                            style={{
                                                color: stopColours[stop.kind],
                                            }}
                                        />
                                        <span>
                                            {stop.name ?? stopLabels[stop.kind]}
                                        </span>
                                        <em>{km(stop.distance_metres)}</em>
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                </details>
            ) : null}
            {details.cues.length > 0 ? (
                <details>
                    <summary>Turns ({details.cues.length})</summary>
                    <ol>
                        {details.cues.map((cue) => {
                            const Icon = turnIcons[cue.turn];

                            return (
                                <li key={`${cue.turn}-${cue.distance_metres}`}>
                                    <button
                                        type="button"
                                        {...focus([
                                            cue.longitude,
                                            cue.latitude,
                                        ])}
                                    >
                                        <Icon size={16} aria-hidden="true" />
                                        <span>{cueText(cue)}</span>
                                        <em>{km(cue.distance_metres)}</em>
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                </details>
            ) : null}
        </div>
    );
}
