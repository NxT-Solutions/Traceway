import { Head } from '@inertiajs/react';
import * as maplibregl from 'maplibre-gl';
import { useEffect, useMemo, useRef, useState } from 'react';
import { type PlayerLine, RoutePlayer } from '@/components/route-player';
import { streetsMapStyle } from '@/lib/maplibre';
import { legColour, legDeviceName } from '@/lib/multisport';
import {
    ensureDirectionArrows,
    resizeDirectionArrows,
} from '@/lib/route-arrows';
import '../../css/planner.css';
import 'maplibre-gl/dist/maplibre-gl.css';

type Coordinate = [number, number];

type SharedRoute = {
    name: string;
    sport: string;
    surface: string;
    elevation: string;
    roads: string;
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
    }>;
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

export default function Share({ route }: { route: SharedRoute }) {
    const mapNode = useRef<HTMLDivElement>(null);
    const mapRef = useRef<maplibregl.Map | null>(null);
    const asideRef = useRef<HTMLElement>(null);
    const [copied, setCopied] = useState(false);
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

            const bounds = route.coordinates.reduce(
                (box, coordinate) => box.extend(coordinate),
                new maplibregl.LngLatBounds(first, first),
            );
            map.fitBounds(bounds, { padding: 80, maxZoom: 15, duration: 0 });
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
    }, [route]);

    return (
        <div className="tw-share">
            <Head title={route.name} />
            <div ref={mapNode} className="map" />
            <RoutePlayer
                mapRef={mapRef}
                lines={playerLines}
                coverRef={asideRef}
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
                    {route.legs.length > 0
                        ? ''
                        : ` · ${surfaceLabel[route.surface] ?? route.surface}`}
                    {route.legs.length === 0 && route.roads === 'quiet'
                        ? ' · Quiet roads'
                        : ''}
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
            </aside>
        </div>
    );
}
