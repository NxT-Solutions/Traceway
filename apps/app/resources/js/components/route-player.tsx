import * as maplibregl from 'maplibre-gl';
import { type RefObject, useEffect, useMemo, useRef, useState } from 'react';
import { type Coordinate, metresBetween } from '@/lib/route-arrows';

export type PlayerLine = {
    label: string;
    color: string;
    coordinates: Coordinate[];
};

type Track = {
    points: Coordinate[];
    /** Metres from the start of the route to each point. */
    at: number[];
    /** The line each point belongs to. */
    line: number[];
    total: number;
};

/** One run through the whole route takes this long, however long the route is. */
const playMs = 24_000;

function trackOf(lines: PlayerLine[]): Track {
    const points: Coordinate[] = [];
    const at: number[] = [];
    const line: number[] = [];
    let total = 0;

    lines.forEach((item, index) => {
        for (const point of item.coordinates) {
            const previous = points[points.length - 1];

            if (previous !== undefined) {
                total += metresBetween(previous, point);
            }

            points.push(point);
            at.push(total);
            line.push(index);
        }
    });

    return { points, at, line, total };
}

/**
 * The point at a distance along the track, the way the athlete is heading
 * there, and the line it is on.
 */
function pointAt(
    track: Track,
    metres: number,
): { point: Coordinate; bearing: number; line: number } | null {
    const { points, at, line } = track;
    const first = points[0];

    if (first === undefined) {
        return null;
    }

    let low = 0;
    let high = at.length - 1;

    while (low < high) {
        const middle = (low + high) >> 1;

        if ((at[middle] ?? 0) < metres) {
            low = middle + 1;
        } else {
            high = middle;
        }
    }

    const index = Math.max(1, low);
    const from = points[index - 1] ?? first;
    const to = points[index] ?? from;
    const start = at[index - 1] ?? 0;
    const span = (at[index] ?? start) - start;
    const share =
        span > 0 ? Math.min(1, Math.max(0, (metres - start) / span)) : 0;
    const latitude = (from[1] * Math.PI) / 180;
    const bearing =
        (Math.atan2((to[0] - from[0]) * Math.cos(latitude), to[1] - from[1]) *
            180) /
        Math.PI;

    return {
        point: [
            from[0] + (to[0] - from[0]) * share,
            from[1] + (to[1] - from[1]) * share,
        ],
        bearing,
        line: line[index] ?? 0,
    };
}

/**
 * The part of the map that nothing covers: the plan panel sits on the left
 * on a wide screen and along the bottom on a phone, and the player card
 * floats over the map too.
 */
function openArea(
    map: maplibregl.Map,
    covers: Array<HTMLElement | null>,
): { top: number; right: number; bottom: number; left: number } {
    const box = map.getContainer().getBoundingClientRect();
    const area = { top: 0, right: 0, bottom: 0, left: 0 };

    for (const node of covers) {
        const cover = node?.getBoundingClientRect();

        if (
            cover === undefined ||
            cover.width === 0 ||
            cover.right <= box.left ||
            cover.left >= box.right ||
            cover.bottom <= box.top ||
            cover.top >= box.bottom
        ) {
            continue;
        }

        if (cover.width < box.width * 0.6 && cover.left - box.left < 80) {
            area.left = Math.max(area.left, cover.right - box.left);
        } else if (cover.top + cover.height / 2 > box.top + box.height / 2) {
            area.bottom = Math.max(area.bottom, box.bottom - cover.top);
        } else {
            area.top = Math.max(area.top, cover.bottom - box.top);
        }
    }

    return area;
}

/**
 * Moves the map when the pin is near the edge of the open area, so the pin
 * stays in sight while the athlete scrubs or plays the route.
 */
function keepInView(
    map: maplibregl.Map,
    point: Coordinate,
    covers: Array<HTMLElement | null>,
    duration: number,
): void {
    const box = map.getContainer().getBoundingClientRect();
    const area = openArea(map, covers);
    const screen = map.project(point);
    const margin = 48;
    const outside =
        screen.x < area.left + margin ||
        screen.x > box.width - area.right - margin ||
        screen.y < area.top + margin ||
        screen.y > box.height - area.bottom - margin;

    if (outside) {
        // An offset moves the pin to the middle of the open area for this
        // move only. Padding would stay on the map and squeeze every later
        // fit of the route.
        map.easeTo({
            center: point,
            offset: [
                (area.left - area.right) / 2,
                (area.top - area.bottom) / 2,
            ],
            duration,
        });
    }
}

function markerElement(): HTMLDivElement {
    const element = document.createElement('div');
    element.className = 'player-pin';
    element.innerHTML =
        '<span class="arrow"><svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M7 2 L11.5 11 L7 8.6 L2.5 11 Z" fill="#fff"/></svg></span>';

    return element;
}

/**
 * A slider along the whole route, like a video player. The start of the
 * slider is the start of the route and the end is the finish. A pin
 * follows the line as the slider moves, and Play runs it from start to
 * finish while the map keeps the pin in view.
 */
export function RoutePlayer({
    mapRef,
    lines,
    coverRef,
    distanceMetres,
    className,
}: {
    mapRef: RefObject<maplibregl.Map | null>;
    lines: PlayerLine[];
    /** A panel that floats over the map, such as the plan. */
    coverRef?: RefObject<HTMLElement | null>;
    /**
     * The length the plan reports. The player measures the line itself, so
     * it scales its numbers to this to show the same total.
     */
    distanceMetres?: number;
    className?: string;
}) {
    const cardRef = useRef<HTMLDivElement>(null);
    const track = useMemo(() => trackOf(lines), [lines]);
    const [metres, setMetres] = useState(0);
    const [playing, setPlaying] = useState(false);
    const markerRef = useRef<maplibregl.Marker | null>(null);
    const frameRef = useRef(0);
    const metresRef = useRef(0);
    const here = pointAt(track, Math.min(metres, track.total));
    const leg = here === null ? undefined : lines[here.line];
    const usable = track.points.length > 1 && track.total > 0;

    const [shownTrack, setShownTrack] = useState(track);

    if (shownTrack !== track) {
        setShownTrack(track);
        setMetres(0);
        setPlaying(false);
    }

    metresRef.current = metres;

    useEffect(() => {
        const map = mapRef.current;

        if (map === null || here === null || !usable || metres <= 0) {
            markerRef.current?.remove();
            markerRef.current = null;

            return;
        }

        if (markerRef.current === null) {
            markerRef.current = new maplibregl.Marker({
                element: markerElement(),
                anchor: 'center',
                rotationAlignment: 'map',
            })
                .setLngLat(here.point)
                .addTo(map);
        }

        markerRef.current.setLngLat(here.point).setRotation(here.bearing);
        markerRef.current
            .getElement()
            .style.setProperty('--pin', leg?.color ?? '#16303a');
    }, [mapRef, here, usable, metres, leg]);

    useEffect(
        () => () => {
            cancelAnimationFrame(frameRef.current);
            markerRef.current?.remove();
            markerRef.current = null;
        },
        [],
    );

    useEffect(() => {
        if (!playing) {
            cancelAnimationFrame(frameRef.current);

            return;
        }

        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        const perMs = track.total / playMs;
        let last = performance.now();

        const step = (now: number) => {
            const next = Math.min(
                track.total,
                metresRef.current + (now - last) * perMs,
            );
            last = now;
            setMetres(next);

            const map = mapRef.current;
            const spot = pointAt(track, next);

            if (map !== null && spot !== null) {
                keepInView(
                    map,
                    spot.point,
                    [coverRef?.current ?? null, cardRef.current],
                    reduced ? 0 : 600,
                );
            }

            if (next >= track.total) {
                setPlaying(false);

                return;
            }

            frameRef.current = requestAnimationFrame(step);
        };

        frameRef.current = requestAnimationFrame(step);

        return () => cancelAnimationFrame(frameRef.current);
    }, [playing, track, mapRef, coverRef]);

    if (!usable) {
        return null;
    }

    const step = Math.max(1, Math.round(track.total / 1000));
    const scale =
        distanceMetres !== undefined && distanceMetres > 0
            ? distanceMetres / track.total
            : 1;
    const km = (along: number) => ((along * scale) / 1000).toFixed(1);

    return (
        <div className={className ?? 'player'}>
            <div
                ref={cardRef}
                className="player-card"
                role="group"
                aria-label="Follow the route"
            >
                <button
                    type="button"
                    className="play"
                    aria-label={playing ? 'Pause' : 'Play the route'}
                    onClick={() => {
                        if (!playing && metres >= track.total) {
                            setMetres(0);
                        }

                        setPlaying((current) => !current);
                    }}
                >
                    {playing ? (
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 14 14"
                            aria-hidden="true"
                        >
                            <rect
                                x="3"
                                y="2"
                                width="3"
                                height="10"
                                rx="1"
                                fill="currentColor"
                            />
                            <rect
                                x="8"
                                y="2"
                                width="3"
                                height="10"
                                rx="1"
                                fill="currentColor"
                            />
                        </svg>
                    ) : (
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 14 14"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 2.5 L11.5 7 L4 11.5 Z"
                                fill="currentColor"
                            />
                        </svg>
                    )}
                </button>
                <div className="player-track">
                    <div className="player-legs" aria-hidden="true">
                        {lines.map((item, index) => {
                            const startAt =
                                track.at[track.line.indexOf(index)] ?? 0;
                            const endAt =
                                track.at[track.line.lastIndexOf(index)] ?? 0;
                            const width =
                                ((endAt - startAt) / track.total) * 100;

                            return width <= 0 ? null : (
                                <i
                                    // Lines keep their order for one route.
                                    key={`${item.label}-${index}`}
                                    style={{
                                        left: `${(startAt / track.total) * 100}%`,
                                        width: `${width}%`,
                                        background: item.color,
                                    }}
                                />
                            );
                        })}
                    </div>
                    <input
                        type="range"
                        min={0}
                        max={Math.round(track.total)}
                        step={step}
                        value={Math.round(metres)}
                        aria-label="Distance along the route"
                        aria-valuetext={`${km(metres)} of ${km(track.total)} km${leg ? `, ${leg.label}` : ''}`}
                        onChange={(event) => {
                            const next = Number(event.target.value);
                            const map = mapRef.current;
                            const spot = pointAt(track, next);
                            setPlaying(false);
                            setMetres(next);

                            if (map !== null && spot !== null) {
                                keepInView(
                                    map,
                                    spot.point,
                                    [
                                        coverRef?.current ?? null,
                                        cardRef.current,
                                    ],
                                    0,
                                );
                            }
                        }}
                    />
                </div>
                <p className="where">
                    <b className="tabular-nums">{km(metres)}</b>
                    <span className="tabular-nums">
                        {` / ${km(track.total)} km`}
                    </span>
                    {leg && lines.length > 1 ? (
                        <small>
                            <i style={{ background: leg.color }} />
                            {leg.label}
                        </small>
                    ) : null}
                </p>
            </div>
        </div>
    );
}
