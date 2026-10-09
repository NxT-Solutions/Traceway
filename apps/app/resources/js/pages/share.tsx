import { Head } from '@inertiajs/react';
import * as maplibregl from 'maplibre-gl';
import { useEffect, useRef } from 'react';
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
};

const sportLabel: Record<string, string> = {
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
    const kilometres = (route.distance_metres / 1000).toFixed(1);

    useEffect(() => {
        const node = mapNode.current;
        const first = route.coordinates[0];

        if (node === null || first === undefined) {
            return;
        }

        const map = new maplibregl.Map({
            container: node,
            style: 'https://tiles.openfreemap.org/styles/liberty',
            center: first,
            zoom: 12,
        });
        map.addControl(
            new maplibregl.NavigationControl({ showCompass: false }),
            'bottom-right',
        );
        map.on('load', () => {
            const solid = {
                type: 'Feature' as const,
                properties: {},
                geometry: {
                    type: 'LineString' as const,
                    coordinates: route.coordinates,
                },
            };
            map.addSource('route', { type: 'geojson', data: solid });
            map.addLayer({
                id: 'route-casing',
                type: 'line',
                source: 'route',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#ffffff', 'line-width': 10 },
            });
            map.addLayer({
                id: 'route',
                type: 'line',
                source: 'route',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#16303a', 'line-width': 4 },
            });
            ensureDirectionArrows(map, [route.coordinates]);

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

            ensureDirectionArrows(map, [route.coordinates]);
        };
        const resizeArrows = () => resizeDirectionArrows(map);
        map.on('zoomend', refreshArrows);
        map.on('resize', resizeArrows);
        window.addEventListener('resize', resizeArrows);

        return () => {
            window.removeEventListener('resize', resizeArrows);
            map.remove();
        };
    }, [route]);

    return (
        <div className="tw-share">
            <Head title={route.name} />
            <div ref={mapNode} className="map" />
            <aside>
                <p className="mark">Traceway</p>
                <h1>{route.name}</h1>
                <p className="facts">
                    <b>{kilometres} km</b>
                    {route.ascent_metres > 0
                        ? ` · ${route.ascent_metres} m`
                        : ''}
                    {` · ${sportLabel[route.sport] ?? route.sport}`}
                    {` · ${surfaceLabel[route.surface] ?? route.surface}`}
                    {route.roads === 'quiet' ? ' · Quiet roads' : ''}
                </p>
                <div className="exports">
                    <a href={route.gpx_url}>GPX</a>
                    <a href={route.fit_url}>FIT</a>
                    <button
                        type="button"
                        onClick={() => {
                            void navigator.clipboard.writeText(
                                window.location.href,
                            );
                        }}
                    >
                        Copy link
                    </button>
                </div>
            </aside>
        </div>
    );
}
