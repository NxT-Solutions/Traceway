import { Head } from '@inertiajs/react';
import * as maplibregl from 'maplibre-gl';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    RouteCues,
    RouteProfile,
    SurfaceBar,
} from '@/components/route-details';
import { type PlayerLine, RoutePlayer } from '@/components/route-player';
import { streetsMapStyle } from '@/lib/maplibre';
import { legColour, legDeviceName } from '@/lib/multisport';
import {
    ensureDirectionArrows,
    resizeDirectionArrows,
} from '@/lib/route-arrows';
import {
    addDetailLayers,
    markChosen,
    pointAlong,
    type RouteDetails,
    syncDetailLayers,
    syncHoverPoint,
} from '@/lib/route-details';
import { keepShareImage, shareImageRoute } from '@/lib/share-image';
import '../../css/planner.css';
import 'maplibre-gl/dist/maplibre-gl.css';

type Coordinate = [number, number];

type SharedRoute = {
    name: string;
    sport: string;
    surface: string;
    elevation: string;
    roads: string;
    shape: 'loop' | 'out_and_back' | 'point_to_point' | 'drawn' | null;
    distance_metres: number;
    ascent_metres: number;
    coordinates: Coordinate[];
    gpx_url: string;
    fit_url: string;
    layout: 'loops' | 'chain' | null;
    legs: Array<{
        discipline: string;
        distance_metres: number;
        ascent_metres: number;
        coordinates: Coordinate[];
        fit_url: string | null;
        color?: string | null;
        details?: RouteDetails | null;
    }>;
    details: RouteDetails | null;
    image_url: string;
};

const sportLabel: Record<string, string> = {
    swim: 'Swim',
    road: 'Road',
    gravel: 'Gravel',
    mtb: 'Mountain bike',
    run: 'Run',
    trail_run: 'Trail run',
    walk: 'Walk',
    hike: 'Hike',
};

const surfaceLabel: Record<string, string> = {
    paved: 'Paved',
    mixed: 'Any surface',
    dirt: 'Dirt',
};

// The planner's words for what the rider asked, shown read-only here.
const shapeLabel: Record<string, string> = {
    loop: 'Loop',
    out_and_back: 'Out and back',
    point_to_point: 'One way',
    drawn: 'Your own points',
};

const hillsLabel: Record<string, string> = {
    neutral: 'Any',
    minimize: 'Flat',
    maximize: 'Hilly',
};

const roadsLabel: Record<string, string> = {
    quiet: 'Quiet first',
    any: 'Any road',
};

const layoutLabel: Record<string, string> = {
    loops: 'Each leg a loop',
    chain: 'One leg after another',
};

/**
 * What the rider asked the planner for, so whoever opens the link knows how
 * the ride was planned.
 */
function plannedWith(
    route: SharedRoute,
    kilometres: string,
): [string, string][] {
    if (route.legs.length > 0) {
        return [
            ['Plan', 'Multisport'],
            ['Legs', layoutLabel[route.layout ?? ''] ?? 'Each leg a loop'],
            ['Length', `${kilometres} km`],
            ['Roads', roadsLabel[route.roads] ?? route.roads],
        ];
    }

    return [
        ['Shape', shapeLabel[route.shape ?? 'loop'] ?? 'Loop'],
        ['Length', `${kilometres} km`],
        ['Sport', sportLabel[route.sport] ?? route.sport],
        ['Surface', surfaceLabel[route.surface] ?? route.surface],
        ['Hills', hillsLabel[route.elevation] ?? route.elevation],
        ['Roads', roadsLabel[route.roads] ?? route.roads],
    ];
}

export default function Share({
    route,
    draw_image: drawImage = false,
    embed = false,
}: {
    route: SharedRoute;
    draw_image?: boolean;
    // Only the map, inside the card a chat app shows after a plan.
    embed?: boolean;
}) {
    const mapNode = useRef<HTMLDivElement>(null);
    const mapRef = useRef<maplibregl.Map | null>(null);
    const asideRef = useRef<HTMLElement>(null);
    const [copied, setCopied] = useState(false);
    const [steepness, setSteepness] = useState(false);
    // A multisport plan shows one land leg's climb at a time.
    const detailLegs = route.legs.flatMap((leg, index) =>
        leg.details ? [index] : [],
    );
    const [detailLegChoice, setDetailLegChoice] = useState(detailLegs[0] ?? 0);
    const detailLeg =
        route.legs.length > 0 ? (route.legs[detailLegChoice] ?? null) : null;
    const shownDetails =
        route.legs.length > 0 ? (detailLeg?.details ?? null) : route.details;
    const shownLine = detailLeg?.coordinates ?? route.coordinates;
    const kilometres = (route.distance_metres / 1000).toFixed(1);
    const legColours = route.legs.map((leg, index) =>
        legColour('bold', index, leg.color),
    );
    const playerLines = useMemo<PlayerLine[]>(
        () =>
            route.legs.length > 0
                ? route.legs
                      .map((leg, index) => ({
                          label: sportLabel[leg.discipline] ?? leg.discipline,
                          color: legColour('bold', index, leg.color),
                          coordinates: leg.coordinates,
                      }))
                      .filter((line) => line.coordinates.length > 1)
                : [
                      {
                          label: sportLabel[route.sport] ?? route.sport,
                          color: '#16303a',
                          coordinates: route.coordinates,
                      },
                  ],
        [route],
    );

    // The rider who saved the route opens their own link before its picture
    // exists, so their browser draws it for the chats that show the link.
    useEffect(() => {
        const id = window.location.pathname.split('/')[2];

        if (!drawImage || id === undefined) {
            return;
        }

        void keepShareImage(
            id,
            shareImageRoute({
                ...route,
                legs: route.legs.map((leg, index) => ({
                    ...leg,
                    color: legColour('bold', index, leg.color),
                })),
            }),
        );
    }, [drawImage, route]);

    const shownLineRef = useRef({
        line: shownLine,
        details: shownDetails,
        steepness,
    });
    shownLineRef.current = {
        line: shownLine,
        details: shownDetails,
        steepness,
    };

    // The layers wait for nothing: before the map loads they are not there
    // yet and this does nothing, and the load handler draws them then.
    useEffect(() => {
        const map = mapRef.current;

        if (map !== null) {
            syncDetailLayers(map, shownLine, shownDetails, steepness);
        }
    }, [shownLine, shownDetails, steepness]);

    // A stop or turn chosen on another leg does not belong on this one.
    useEffect(() => {
        const map = mapRef.current;

        if (map !== null && shownLine.length > 0) {
            markChosen(map, null);
        }
    }, [shownLine]);

    // Fly to a stop or a turn, clear of the card over the map.
    const showOnMap = (point: Coordinate) => {
        const map = mapRef.current;
        const card = asideRef.current?.getBoundingClientRect();

        if (map === null) {
            return;
        }

        const beside =
            card !== undefined && card.right < window.innerWidth * 0.6;
        map.flyTo({
            center: point,
            zoom: Math.max(map.getZoom(), 16),
            padding: {
                top: !beside && card ? card.bottom + 16 : 48,
                left: beside && card ? card.right + 24 : 24,
                right: 48,
                bottom: 96,
            },
        });
        markChosen(map, point);
    };

    useEffect(() => {
        const node = mapNode.current;
        const first = route.coordinates[0];

        if (node === null || first === undefined) {
            return;
        }

        const map = new maplibregl.Map({
            container: node,
            style: streetsMapStyle,
            center: first,
            zoom: 12,
            attributionControl: { compact: true },
            // In a chat, scrolling moves the chat; a modifier key or two
            // fingers move the map.
            cooperativeGestures: embed,
        });
        mapRef.current = map;
        map.addControl(
            new maplibregl.NavigationControl({ showCompass: false }),
            'bottom-right',
        );
        const arrowLines =
            route.legs.length > 0
                ? route.legs
                      .filter((leg) => leg.discipline !== 'swim')
                      .map((leg) => leg.coordinates)
                : [route.coordinates];
        map.on('load', () => {
            const parts =
                route.legs.length > 0
                    ? route.legs
                          .map((leg, index) => ({
                              discipline: leg.discipline,
                              coordinates: leg.coordinates,
                              color: legColour('bold', index, leg.color),
                          }))
                          .filter((leg) => leg.coordinates.length > 1)
                    : [
                          {
                              discipline: route.sport,
                              coordinates: route.coordinates,
                              color: '#16303a',
                          },
                      ];
            map.addSource('route', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: parts.map((part) => ({
                        type: 'Feature' as const,
                        properties: {
                            discipline: part.discipline,
                            color: part.color,
                        },
                        geometry: {
                            type: 'LineString' as const,
                            coordinates: part.coordinates,
                        },
                    })),
                },
            });
            map.addLayer({
                id: 'route-casing',
                type: 'line',
                source: 'route',
                filter: ['!=', ['get', 'discipline'], 'swim'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#ffffff', 'line-width': 10 },
            });
            map.addLayer({
                id: 'route',
                type: 'line',
                source: 'route',
                filter: ['!=', ['get', 'discipline'], 'swim'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': ['get', 'color'], 'line-width': 4 },
            });
            map.addLayer({
                id: 'route-swim',
                type: 'line',
                source: 'route',
                filter: ['==', ['get', 'discipline'], 'swim'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': ['get', 'color'],
                    'line-width': 4,
                    'line-dasharray': [1, 1.2],
                },
            });
            ensureDirectionArrows(map, arrowLines);
            addDetailLayers(map);
            syncDetailLayers(
                map,
                shownLineRef.current.line,
                shownLineRef.current.details,
                shownLineRef.current.steepness,
            );

            const bounds = route.coordinates.reduce(
                (box, coordinate) => box.extend(coordinate),
                new maplibregl.LngLatBounds(first, first),
            );
            map.fitBounds(bounds, {
                padding: embed ? 28 : 80,
                maxZoom: 15,
                duration: 0,
            });

            if (embed) {
                // When the chat turns or resizes the card, the route stays
                // in view, until the viewer moves the map themselves.
                let moved = false;
                map.on('movestart', (event) => {
                    moved = moved || event.originalEvent !== undefined;
                });
                map.on('resize', () => {
                    if (!moved) {
                        map.fitBounds(bounds, {
                            padding: 28,
                            maxZoom: 15,
                            duration: 0,
                        });
                    }
                });

                // The card shows this map only once it is drawn, and keeps
                // its own drawing until then.
                map.once('idle', () =>
                    window.parent.postMessage(
                        { type: 'traceway:map-ready' },
                        '*',
                    ),
                );
            }
        });
        const refreshArrows = () => {
            if (!map.isStyleLoaded()) {
                return;
            }

            ensureDirectionArrows(map, arrowLines);
        };
        const resizeArrows = () => resizeDirectionArrows(map);
        map.on('zoomend', refreshArrows);
        map.on('resize', resizeArrows);
        window.addEventListener('resize', resizeArrows);

        return () => {
            window.removeEventListener('resize', resizeArrows);
            map.remove();
            mapRef.current = null;
        };
    }, [route, embed]);

    if (embed) {
        return (
            <div className="tw-share tw-share-embed">
                <Head title={`${route.name} · ${kilometres} km`} />
                <div ref={mapNode} className="map" />
            </div>
        );
    }

    return (
        <div className="tw-share">
            <Head title={`${route.name} · ${kilometres} km`} />
            <div ref={mapNode} className="map" />
            <RoutePlayer
                mapRef={mapRef}
                lines={playerLines}
                coverRef={asideRef}
                distanceMetres={route.distance_metres}
            />
            <aside ref={asideRef}>
                <p className="mark">Traceway</p>
                <h1>{route.name}</h1>
                <p className="facts">
                    <b>{kilometres} km</b>
                    {route.ascent_metres > 0
                        ? ` · ${route.ascent_metres} m`
                        : ''}
                    {route.legs.length > 0
                        ? ` · Multisport · ${route.legs.length} legs`
                        : ` · ${sportLabel[route.sport] ?? route.sport}`}
                </p>
                {route.legs.length > 0 ? (
                    <ol className="legs">
                        {route.legs.map((leg, index) => (
                            <li key={`${leg.discipline}-${index}`}>
                                <i
                                    aria-hidden="true"
                                    style={{
                                        background: legColours[index],
                                    }}
                                />
                                <b>
                                    {sportLabel[leg.discipline] ??
                                        leg.discipline}
                                </b>
                                <span>
                                    {(leg.distance_metres / 1000).toFixed(1)} km
                                </span>
                            </li>
                        ))}
                    </ol>
                ) : null}
                {detailLegs.length > 1 ? (
                    <div
                        className="tw-details-legs"
                        role="tablist"
                        aria-label="Leg to show"
                    >
                        {detailLegs.map((index) => (
                            <button
                                key={index}
                                type="button"
                                role="tab"
                                aria-selected={detailLegChoice === index}
                                onClick={() => setDetailLegChoice(index)}
                            >
                                {`${index + 1} ${sportLabel[route.legs[index]?.discipline ?? ''] ?? ''}`}
                            </button>
                        ))}
                    </div>
                ) : null}
                {shownDetails === null ? null : (
                    <div className="tw-details">
                        <RouteProfile
                            details={shownDetails}
                            steepness={steepness}
                            onSteepness={setSteepness}
                            onHover={(metres) => {
                                const map = mapRef.current;

                                if (map !== null) {
                                    syncHoverPoint(
                                        map,
                                        metres === null
                                            ? null
                                            : pointAlong(shownLine, metres),
                                    );
                                }
                            }}
                        />
                        <SurfaceBar details={shownDetails} />
                        <RouteCues
                            details={shownDetails}
                            onFocusPoint={(point) => {
                                const map = mapRef.current;

                                if (map !== null) {
                                    syncHoverPoint(map, point);
                                }
                            }}
                            onSelectPoint={showOnMap}
                        />
                    </div>
                )}
                <section className="planned" aria-labelledby="planned-title">
                    <h2 id="planned-title">How it was planned</h2>
                    <dl>
                        {plannedWith(route, kilometres).map(
                            ([label, value]) => (
                                <div key={label}>
                                    <dt>{label}</dt>
                                    <dd>{value}</dd>
                                </div>
                            ),
                        )}
                    </dl>
                </section>
                <div className="exports">
                    <a href={route.gpx_url}>GPX</a>
                    {route.legs.length > 0 ? (
                        route.legs.map((leg, index) =>
                            leg.fit_url === null ? null : (
                                <a
                                    key={`${leg.discipline}-${index}`}
                                    href={leg.fit_url}
                                    title={legDeviceName(
                                        route.name,
                                        index + 1,
                                        leg.discipline,
                                    )}
                                >
                                    {`FIT ${index + 1} ${sportLabel[leg.discipline] ?? leg.discipline}`}
                                </a>
                            ),
                        )
                    ) : (
                        <a href={route.fit_url}>FIT</a>
                    )}
                    <button
                        type="button"
                        onClick={() => {
                            void navigator.clipboard
                                .writeText(window.location.href)
                                .then(() => setCopied(true));
                        }}
                    >
                        {copied ? 'Copied' : 'Copy link'}
                    </button>
                </div>
                <p className="credit">
                    Planned with Traceway, free and kept running by{' '}
                    <a href="/support">donations</a>.
                </p>
            </aside>
        </div>
    );
}
