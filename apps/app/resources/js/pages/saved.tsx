import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bike, Footprints, Mountain } from 'lucide-react';
import type { GeoJSONSource, MapLayerMouseEvent } from 'maplibre-gl';
import * as maplibregl from 'maplibre-gl';
import {
    type PointerEvent as ReactPointerEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { MultisportIcon } from '@/components/multisport-icon';
import { RouteProfile, SurfaceBar } from '@/components/route-details';
import { SendToDevice } from '@/components/send-to-device';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { themedMapStyle } from '@/lib/maplibre';
import {
    ensureDirectionArrows,
    resizeDirectionArrows,
} from '@/lib/route-arrows';
import { isRouteDetails, type RouteDetails } from '@/lib/route-details';
import '../../css/planner.css';
import 'maplibre-gl/dist/maplibre-gl.css';

type Coordinate = [number, number];

type SavedRoute = {
    id: string;
    name: string;
    sport: string;
    surface: string;
    elevation: string;
    roads: string;
    distance_metres: number;
    ascent_metres: number;
    url: string;
    fit_url: string;
    created_at: string | null;
    coordinates: Coordinate[];
    layout: 'loops' | 'chain' | null;
    legs: Array<{
        discipline: string;
        distance_metres: number;
        fit_url: string | null;
    }>;
};

const sportLabel: Record<string, string> = {
    multisport: 'Multisport',
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

const hillLabel: Record<string, string> = {
    minimize: 'Flatter',
    neutral: 'Any hills',
    maximize: 'Hillier',
};

const roadLabel: Record<string, string> = {
    quiet: 'Quiet roads',
    any: 'Any roads',
};

/**
 * A multisport plan files under its own sport, not the sport of its first leg.
 */
function sportKey(route: SavedRoute): string {
    return route.legs.length > 0 ? 'multisport' : route.sport;
}

function SportMark({ sport }: { sport: string }) {
    const Icon =
        sport === 'multisport'
            ? MultisportIcon
            : sport === 'mtb'
              ? Mountain
              : sport === 'road' || sport === 'gravel'
                ? Bike
                : Footprints;

    return (
        <span className="sport-mark" title={sportLabel[sport] ?? sport}>
            <Icon size={16} aria-hidden="true" />
            <span className="vh">{sportLabel[sport] ?? sport}</span>
        </span>
    );
}

function routeSpecs(route: SavedRoute): string {
    if (route.legs.length > 0) {
        return route.legs
            .map(
                (leg) =>
                    `${sportLabel[leg.discipline] ?? leg.discipline} ${(leg.distance_metres / 1000).toFixed(1)} km`,
            )
            .join(' → ');
    }

    return [
        sportLabel[route.sport] ?? route.sport,
        surfaceLabel[route.surface] ?? route.surface,
        hillLabel[route.elevation] ?? route.elevation,
        roadLabel[route.roads] ?? route.roads,
    ].join(' · ');
}

function useWide(): boolean {
    const [wide, setWide] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(min-width: 1024px)').matches,
    );

    useEffect(() => {
        const query = window.matchMedia('(min-width: 1024px)');
        const apply = () => setWide(query.matches);
        apply();
        query.addEventListener('change', apply);

        return () => query.removeEventListener('change', apply);
    }, []);

    return wide;
}

function kilometres(metres: number): string {
    return `${(metres / 1000).toFixed(1)} km`;
}

function writtenOn(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function RouteShape({ coordinates }: { coordinates: Coordinate[] }) {
    if (coordinates.length < 2) {
        return null;
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const [x, y] of coordinates) {
        minX = Math.min(minX, x);
        maxX = Math.max(maxX, x);
        minY = Math.min(minY, y);
        maxY = Math.max(maxY, y);
    }

    const width = Math.max(maxX - minX, 0.0001);
    const height = Math.max(maxY - minY, 0.0001);
    const points = coordinates
        .map(([x, y]) => {
            const px = ((x - minX) / width) * 76 + 12;
            const py = (1 - (y - minY) / height) * 76 + 12;

            return `${px},${py}`;
        })
        .join(' ');

    return (
        <svg
            viewBox="0 0 100 100"
            preserveAspectRatio="xMidYMid meet"
            aria-hidden="true"
        >
            <polyline
                points={points}
                fill="none"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinejoin="round"
                strokeLinecap="round"
            />
        </svg>
    );
}

function Thumb({
    route,
    image,
}: {
    route: SavedRoute;
    image: string | undefined;
}) {
    return (
        <div className="thumb">
            <RouteShape coordinates={route.coordinates} />
            {image ? <img src={image} alt="" /> : null}
        </div>
    );
}

type LibraryLayout = 'grid' | 'map' | 'list';

const layoutKey = 'traceway.library.layout';

function readLayout(): LibraryLayout {
    if (typeof window === 'undefined') {
        return 'grid';
    }

    try {
        const value = window.localStorage.getItem(layoutKey);

        if (value === 'grid' || value === 'map' || value === 'list') {
            return value;
        }
    } catch {
        return 'grid';
    }

    return 'grid';
}

function LayoutSwitch({
    value,
    onChange,
}: {
    value: LibraryLayout;
    onChange: (next: LibraryLayout) => void;
}) {
    const options: Array<{ id: LibraryLayout; label: string }> = [
        { id: 'grid', label: 'Grid' },
        { id: 'map', label: 'Map' },
        { id: 'list', label: 'List' },
    ];

    return (
        <div
            className="layouts"
            role="tablist"
            aria-label="Layout"
            onKeyDown={(event) => {
                if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                    return;
                }

                event.preventDefault();
                const index = options.findIndex(
                    (option) => option.id === value,
                );
                const delta = event.key === 'ArrowRight' ? 1 : -1;
                const next =
                    options[(index + delta + options.length) % options.length];

                if (next === undefined) {
                    return;
                }

                onChange(next.id);
                const buttons = event.currentTarget.querySelectorAll('button');
                buttons[
                    (index + delta + options.length) % options.length
                ]?.focus();
            }}
        >
            {options.map((option) => (
                <button
                    key={option.id}
                    type="button"
                    role="tab"
                    id={`layout-${option.id}`}
                    aria-selected={value === option.id}
                    tabIndex={value === option.id ? 0 : -1}
                    onClick={() => onChange(option.id)}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

export default function Saved({ routes }: { routes: SavedRoute[] }) {
    const { resolvedAppearance } = useAppearance();
    const { auth } = usePage<{ auth: { user: { name: string } } }>().props;
    const rider = auth.user.name;
    const initials = rider
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase();
    const wide = useWide();
    const [layout, setLayout] = useState<LibraryLayout>(readLayout);
    const [query, setQuery] = useState('');
    const [sport, setSport] = useState('all');
    const [surface, setSurface] = useState('all');
    const [hills, setHills] = useState('all');
    const [roads, setRoads] = useState('all');
    const [opened, setOpened] = useState<string | null>(null);
    const [sheet, setSheet] = useState<'peek' | 'list' | 'detail'>('peek');
    const [draggingSheet, setDraggingSheet] = useState(false);
    const sheetRef = useRef<HTMLElement | null>(null);
    const sheetDrag = useRef<{
        y: number;
        height: number;
        moved: boolean;
        lastY: number;
        lastT: number;
        velocity: number;
    } | null>(null);
    const [copied, setCopied] = useState(false);
    const [images, setImages] = useState<Record<string, string>>({});
    const redrawLibrary = useRef<() => void>(() => {});
    const appearanceRef = useRef(resolvedAppearance);
    const libraryEventsBound = useRef(false);
    const mapNode = useRef<HTMLDivElement>(null);
    const mapRef = useRef<maplibregl.Map | null>(null);
    const geolocateRef = useRef<maplibregl.GeolocateControl | null>(null);
    const paddingRef = useRef<
        (map: maplibregl.Map) => maplibregl.PaddingOptions
    >(() => ({ top: 80, right: 80, bottom: 80, left: 80 }));
    const arrowLinesRef = useRef<Coordinate[][]>([]);
    const visible = useMemo(
        () =>
            routes.filter((route) => {
                const named = route.name
                    .toLowerCase()
                    .includes(query.trim().toLowerCase());

                return (
                    named &&
                    (sport === 'all' || sportKey(route) === sport) &&
                    (surface === 'all' || route.surface === surface) &&
                    (hills === 'all' || route.elevation === hills) &&
                    (roads === 'all' || route.roads === roads)
                );
            }),
        [routes, query, sport, surface, hills, roads],
    );
    const openedRoute = visible.find((route) => route.id === opened) ?? null;
    const mapLayout = !wide || layout === 'map';
    const showMap = mapLayout || openedRoute !== null;

    function dismissIfBlank(event: { target: EventTarget | null }): void {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        if (target.closest('button, a, input, .sheet-card, .dock-detail')) {
            return;
        }

        setOpened(null);
        setCopied(false);
    }

    function chooseLayout(next: LibraryLayout): void {
        setLayout(next);

        try {
            window.localStorage.setItem(layoutKey, next);
        } catch {
            // The view still changes when storage is blocked.
        }
        setOpened(null);
        setSheet('peek');
        setCopied(false);
    }

    useEffect(() => {
        if (opened !== null && !visible.some((route) => route.id === opened)) {
            setOpened(null);
            setSheet('list');
        }
    }, [visible, opened]);

    useEffect(() => {
        if (routes.length === 0) {
            return;
        }

        const node = document.createElement('div');
        node.style.cssText =
            'position:fixed;left:0;top:0;width:384px;height:300px;visibility:hidden;pointer-events:none';
        document.body.appendChild(node);
        const mapOptions = {
            container: node,
            style: themedMapStyle(resolvedAppearance),
            interactive: false,
            attributionControl: false as const,
            preserveDrawingBuffer: true,
            fadeDuration: 0,
        };
        const map = new maplibregl.Map(mapOptions);
        let stopped = false;
        map.on('load', () => {
            map.addSource('shot', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] },
            });
            map.addLayer({
                id: 'shot-case',
                type: 'line',
                source: 'shot',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#ffffff', 'line-width': 7 },
            });
            map.addLayer({
                id: 'shot',
                type: 'line',
                source: 'shot',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#16303a', 'line-width': 4 },
            });
            const source = map.getSource('shot');
            if (source?.type !== 'geojson') {
                return;
            }

            const take = (index: number) => {
                const route = routes[index];

                if (
                    stopped ||
                    route === undefined ||
                    route.coordinates.length < 2
                ) {
                    if (!stopped && route !== undefined) {
                        take(index + 1);
                    } else {
                        map.remove();
                        node.remove();
                    }

                    return;
                }

                (source as GeoJSONSource).setData({
                    type: 'Feature',
                    properties: {},
                    geometry: {
                        type: 'LineString',
                        coordinates: route.coordinates,
                    },
                });
                const first = route.coordinates[0];
                if (first === undefined) {
                    return;
                }
                const bounds = route.coordinates.reduce(
                    (box, coordinate) => box.extend(coordinate),
                    new maplibregl.LngLatBounds(first, first),
                );
                map.resize();
                map.fitBounds(bounds, {
                    padding: 16,
                    duration: 0,
                    maxZoom: 15,
                });
                let shot = false;
                const shoot = () => {
                    if (shot || stopped) {
                        return;
                    }
                    shot = true;
                    setImages((current) => ({
                        ...current,
                        [route.id]: map
                            .getCanvas()
                            .toDataURL('image/jpeg', 0.82),
                    }));
                    take(index + 1);
                };
                // "idle" waits for the tiles to load and paint. A shot taken
                // before that is a blank canvas that hides the outline below.
                map.once('idle', () => {
                    requestAnimationFrame(shoot);
                });
                window.setTimeout(shoot, 6000);
            };
            take(0);
        });

        return () => {
            stopped = true;
            map.remove();
            node.remove();
        };
    }, [routes, resolvedAppearance]);

    useEffect(() => {
        if (!showMap) {
            return;
        }

        const node = mapNode.current;

        if (node === null) {
            return;
        }

        const map = new maplibregl.Map({
            container: node,
            style: themedMapStyle(appearanceRef.current),
            center: [4.4, 50.8],
            zoom: 7,
            attributionControl: false,
        });
        const geolocate = new maplibregl.GeolocateControl({
            positionOptions: { enableHighAccuracy: true },
            trackUserLocation: false,
            showUserLocation: true,
            showAccuracyCircle: true,
            zoomToUserAccuracy: false,
        });
        geolocate.on(
            'geolocate',
            (event: { coords: GeolocationCoordinates }) => {
                const reduced = window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches;
                map.easeTo({
                    center: [event.coords.longitude, event.coords.latitude],
                    zoom: Math.max(map.getZoom(), 15),
                    padding: paddingRef.current(map),
                    duration: reduced ? 0 : 180,
                });
            },
        );
        geolocateRef.current = geolocate;
        map.addControl(geolocate, 'top-right');
        mapRef.current = map;
        const refreshArrows = () => {
            if (!map.isStyleLoaded()) {
                return;
            }

            ensureDirectionArrows(map, arrowLinesRef.current);
        };
        const resizeArrows = () => resizeDirectionArrows(map);
        map.on('zoomend', refreshArrows);
        map.on('resize', resizeArrows);
        map.on('style.load', () => redrawLibrary.current());
        window.addEventListener('resize', resizeArrows);

        return () => {
            window.removeEventListener('resize', resizeArrows);
            map.remove();
            mapRef.current = null;
            libraryEventsBound.current = false;
        };
    }, [showMap]);

    useEffect(() => {
        if (appearanceRef.current === resolvedAppearance) {
            return;
        }

        appearanceRef.current = resolvedAppearance;
        mapRef.current?.setStyle(themedMapStyle(resolvedAppearance));
    }, [resolvedAppearance]);

    const libraryPadding = useCallback(
        (map: maplibregl.Map): maplibregl.PaddingOptions => {
            const narrow = window.matchMedia('(max-width: 1023px)').matches;

            if (!narrow) {
                return {
                    top: 80,
                    right: 80,
                    bottom: 80,
                    left: layout === 'map' ? 544 : openedRoute ? 440 : 80,
                };
            }

            const canvas = map.getContainer().getBoundingClientRect();
            const sheetBox = sheetRef.current?.getBoundingClientRect();
            const cover =
                sheetBox === undefined
                    ? 96
                    : Math.max(0, canvas.bottom - sheetBox.top);
            const room = canvas.height - cover;
            const edge = room < 220 ? 16 : 28;
            const bottom = Math.min(
                Math.max(24, canvas.height - edge - 64),
                cover + 36,
            );

            return { top: 72, right: 64, bottom, left: edge };
        },
        [layout, openedRoute],
    );

    useEffect(() => {
        const map = mapRef.current;

        if (map === null || !showMap) {
            return;
        }

        const draw = () => {
            const focus = openedRoute;
            const lines = focus ? [focus] : visible;
            const data = {
                type: 'FeatureCollection' as const,
                features: lines
                    .filter((route) => route.coordinates.length > 1)
                    .map((route) => ({
                        type: 'Feature' as const,
                        properties: { id: route.id },
                        geometry: {
                            type: 'LineString' as const,
                            coordinates: route.coordinates,
                        },
                    })),
            };

            if (map.getSource('library') === undefined) {
                map.addSource('library', { type: 'geojson', data });
                map.addLayer({
                    id: 'library-case',
                    type: 'line',
                    source: 'library',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: { 'line-color': '#ffffff', 'line-width': 8 },
                });
                map.addLayer({
                    id: 'library-line',
                    type: 'line',
                    source: 'library',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: { 'line-color': '#16303a', 'line-width': 4 },
                });
                map.addLayer({
                    id: 'library-hit',
                    type: 'line',
                    source: 'library',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: {
                        'line-color': '#16303a',
                        'line-width': 22,
                        'line-opacity': 0,
                    },
                });
            }

            if (!libraryEventsBound.current) {
                libraryEventsBound.current = true;
                map.on('click', 'library-hit', (event: MapLayerMouseEvent) => {
                    const id = event.features?.[0]?.properties?.id;

                    if (typeof id === 'string') {
                        setOpened(id);
                        setSheet('detail');
                        setCopied(false);
                    }
                });
                map.on('click', (event: MapLayerMouseEvent) => {
                    if (map.getLayer('library-hit') === undefined) {
                        return;
                    }

                    const hits = map.queryRenderedFeatures(event.point, {
                        layers: ['library-hit'],
                    });

                    if (hits.length === 0) {
                        setOpened(null);
                        setCopied(false);
                    }
                });
            }

            const source = map.getSource('library');

            if (source?.type === 'geojson') {
                (source as GeoJSONSource).setData(data);
            }

            const arrowLines = data.features.map(
                (feature) => feature.geometry.coordinates,
            );
            arrowLinesRef.current = arrowLines;
            ensureDirectionArrows(map, arrowLines);

            const target =
                focus?.coordinates ??
                lines.flatMap((route) => route.coordinates);
            const first = target[0];

            if (first === undefined) {
                return;
            }

            const bounds = target.reduce(
                (box, coordinate) => box.extend(coordinate),
                new maplibregl.LngLatBounds(first, first),
            );
            map.fitBounds(bounds, {
                padding: libraryPadding(map),
                maxZoom: 14,
                duration: 180,
            });
        };

        redrawLibrary.current = draw;

        if (map.isStyleLoaded()) {
            draw();
        } else {
            map.once('load', draw);
        }
    }, [showMap, visible, openedRoute, libraryPadding]);

    paddingRef.current = libraryPadding;

    function sheetLimits(): { peek: number; full: number } {
        const peek = 118;
        const full = Math.round(Math.min(window.innerHeight * 0.72, 720));

        return { peek, full: Math.max(peek + 80, full) };
    }

    useEffect(() => {
        const node = sheetRef.current;

        if (node === null || !showMap || wide) {
            return;
        }

        const observer = new ResizeObserver(() => {
            const map = mapRef.current;

            if (map === null || !map.isStyleLoaded()) {
                return;
            }

            const lines = openedRoute ? [openedRoute] : visible;
            const target = lines.flatMap((route) => route.coordinates);
            const first = target[0];

            if (first === undefined) {
                return;
            }

            const bounds = target.reduce(
                (box, coordinate) => box.extend(coordinate),
                new maplibregl.LngLatBounds(first, first),
            );
            map.fitBounds(bounds, {
                padding: libraryPadding(map),
                maxZoom: 14,
                duration: 0,
            });
        });
        observer.observe(node);

        return () => observer.disconnect();
    }, [showMap, visible, openedRoute, wide, libraryPadding]);

    function onSheetDown(event: ReactPointerEvent<HTMLButtonElement>): void {
        const node = sheetRef.current;

        if (node === null || wide) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        sheetDrag.current = {
            y: event.clientY,
            height: node.getBoundingClientRect().height,
            moved: false,
            lastY: event.clientY,
            lastT: event.timeStamp,
            velocity: 0,
        };
    }

    function onSheetMove(event: ReactPointerEvent<HTMLButtonElement>): void {
        const current = sheetDrag.current;
        const node = sheetRef.current;

        if (current === null || node === null) {
            return;
        }

        const delta = event.clientY - current.y;

        if (Math.abs(delta) > 6) {
            current.moved = true;
            node.classList.add('dragging');
            setDraggingSheet(true);
        }

        current.velocity =
            (event.clientY - current.lastY) /
            Math.max(1, event.timeStamp - current.lastT);
        current.lastY = event.clientY;
        current.lastT = event.timeStamp;

        if (!current.moved) {
            return;
        }

        const { peek, full } = sheetLimits();
        const next = Math.min(full, Math.max(peek, current.height - delta));
        node.style.height = `${next}px`;
    }

    function onSheetUp(): void {
        const current = sheetDrag.current;
        const node = sheetRef.current;
        sheetDrag.current = null;
        setDraggingSheet(false);

        if (current === null || node === null) {
            return;
        }

        const height = node.getBoundingClientRect().height;
        const { peek, full } = sheetLimits();
        node.classList.remove('dragging');
        node.style.height = '';
        const pastHalfway = height > (peek + full) / 2;
        const flungOpen = current.velocity < -0.35;
        const flungShut = current.velocity > 0.35;
        const shouldOpen = flungShut ? false : flungOpen || pastHalfway;

        if (sheet === 'detail') {
            if (!current.moved || !shouldOpen) {
                setSheet('list');
            }

            return;
        }

        if (!current.moved) {
            setSheet(sheet === 'list' ? 'peek' : 'list');

            return;
        }

        setSheet(shouldOpen ? 'list' : 'peek');
    }

    function openRoute(route: SavedRoute): void {
        setCopied(false);
        setOpened(route.id);
        setSheet('detail');
    }

    return (
        <div className="tw-planner" data-theme={resolvedAppearance}>
            <Head title="Saved routes" />
            <header className="hdr">
                <Link
                    className="brand"
                    href="/dashboard"
                    aria-label="Traceway planner"
                >
                    <svg
                        width="26"
                        height="26"
                        viewBox="0 0 28 28"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M6 23 V12 a6 6 0 0 1 6 -6 h4 a6 6 0 0 1 6 6 v3"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                        <circle
                            cx="22"
                            cy="22"
                            r="3"
                            fill="oklch(0.78 0.17 128)"
                        />
                    </svg>
                    <span>Traceway</span>
                </Link>
                <div className="hdr-end">
                    <Link className="plan-nav" href="/dashboard">
                        <span className="long">Plan your route</span>
                        <span className="short">Plan</span>
                    </Link>
                    <Link
                        className="saved-nav"
                        href="/saved"
                        aria-current="page"
                        aria-label="Saved routes"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 2.5h8a1 1 0 0 1 1 1V14l-5-2.5L3 14V3.5a1 1 0 0 1 1-1z"
                                stroke="currentColor"
                                strokeWidth="1.4"
                                strokeLinejoin="round"
                            />
                        </svg>
                        <span>Saved routes</span>
                    </Link>
                    <Link
                        className="acct"
                        href="/settings/profile"
                        aria-label="Account and settings"
                    >
                        <span className="name">{rider}</span>
                        <span className="avatar">{initials}</span>
                    </Link>
                </div>
            </header>
            {wide && layout !== 'map' && openedRoute === null ? (
                <div className="tw-library">
                    <div className="page">
                        <div className="top">
                            <h1>Saved routes</h1>
                        </div>
                        <div className="bar">
                            <RouteFilters
                                routes={routes}
                                query={query}
                                sport={sport}
                                surface={surface}
                                hills={hills}
                                roads={roads}
                                onQuery={setQuery}
                                onSport={setSport}
                                onSurface={setSurface}
                                onHills={setHills}
                                onRoads={setRoads}
                            />
                            <LayoutSwitch
                                value={layout}
                                onChange={chooseLayout}
                            />
                            <Link className="new" href="/dashboard">
                                New route
                            </Link>
                        </div>
                        {visible.length === 0 ? (
                            <p className="note">No saved routes match.</p>
                        ) : layout === 'list' ? (
                            <div className="list">
                                {visible.map((route) => (
                                    <button
                                        key={route.id}
                                        type="button"
                                        className="sheet-card"
                                        onClick={() => openRoute(route)}
                                    >
                                        <Thumb
                                            route={route}
                                            image={images[route.id]}
                                        />
                                        <span>
                                            <span className="date">
                                                {writtenOn(route.created_at)}
                                            </span>
                                            <span className="title-line">
                                                <SportMark
                                                    sport={sportKey(route)}
                                                />
                                                <span className="title">
                                                    {route.name}
                                                </span>
                                            </span>
                                            <span className="stats">
                                                {kilometres(
                                                    route.distance_metres,
                                                )}
                                                {route.ascent_metres > 0
                                                    ? ` · ${route.ascent_metres} m`
                                                    : ''}
                                            </span>
                                            <span className="specs">
                                                {routeSpecs(route)}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <div className="grid">
                                {visible.map((route) => (
                                    <button
                                        key={route.id}
                                        type="button"
                                        className="card"
                                        onClick={() => openRoute(route)}
                                    >
                                        <Thumb
                                            route={route}
                                            image={images[route.id]}
                                        />
                                        <span className="body">
                                            <span className="date">
                                                {writtenOn(route.created_at)}
                                            </span>
                                            <span className="title-line">
                                                <SportMark
                                                    sport={sportKey(route)}
                                                />
                                                <span className="title">
                                                    {route.name}
                                                </span>
                                            </span>
                                            <span className="stats">
                                                {kilometres(
                                                    route.distance_metres,
                                                )}
                                                {route.ascent_metres > 0
                                                    ? ` · ${route.ascent_metres} m`
                                                    : ''}
                                            </span>
                                            <span className="specs">
                                                {routeSpecs(route)}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                <div className={`tw-library mapmode${wide ? '' : ' sheeted'}`}>
                    <div ref={mapNode} className="map" />
                    <div className="vig" />
                    <div className="mapctl">
                        <div className="ctl">
                            <button
                                type="button"
                                aria-label="Zoom in"
                                onClick={() => mapRef.current?.zoomIn()}
                            >
                                +
                            </button>
                            <button
                                type="button"
                                aria-label="Zoom out"
                                onClick={() => mapRef.current?.zoomOut()}
                            >
                                −
                            </button>
                        </div>
                        <div className="ctl">
                            <button
                                type="button"
                                aria-label="Fit the route"
                                onClick={() => {
                                    const map = mapRef.current;

                                    if (map === null || !map.isStyleLoaded()) {
                                        return;
                                    }

                                    const lines = openedRoute
                                        ? [openedRoute]
                                        : visible;
                                    const target = lines.flatMap(
                                        (route) => route.coordinates,
                                    );
                                    const first = target[0];

                                    if (first === undefined) {
                                        return;
                                    }

                                    const bounds = target.reduce(
                                        (box, coordinate) =>
                                            box.extend(coordinate),
                                        new maplibregl.LngLatBounds(
                                            first,
                                            first,
                                        ),
                                    );
                                    map.fitBounds(bounds, {
                                        padding: libraryPadding(map),
                                        maxZoom: 14,
                                        duration: 180,
                                    });
                                }}
                            >
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M8 2 L10 6 H6 Z M8 14 L6 10 H10 Z M2 8 L6 6 V10 Z M14 8 L10 10 V6 Z"
                                        fill="currentColor"
                                    />
                                </svg>
                            </button>
                        </div>
                        <div className="ctl">
                            <button
                                type="button"
                                aria-label="Find my location"
                                onClick={() => {
                                    const control = geolocateRef.current;

                                    if (control === null) {
                                        return;
                                    }

                                    const map = mapRef.current;

                                    if (map !== null) {
                                        control.options.fitBoundsOptions = {
                                            padding: libraryPadding(map),
                                            maxZoom: 16,
                                        };
                                    }

                                    void control.trigger();
                                }}
                            >
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="8"
                                        cy="8"
                                        r="3"
                                        stroke="currentColor"
                                        strokeWidth="1.6"
                                    />
                                    <path
                                        d="M8 1.5 V4 M8 12 V14.5 M1.5 8 H4 M12 8 H14.5"
                                        stroke="currentColor"
                                        strokeWidth="1.6"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p className="attr">
                        MapLibre · OpenFreeMap · © OpenMapTiles · Data from
                        OpenStreetMap
                    </p>
                    {wide && layout === 'map' ? (
                        <aside
                            className="dock"
                            aria-label="Saved routes"
                            onClick={dismissIfBlank}
                            onKeyDown={dismissIfBlank}
                        >
                            <div className="dock-bar">
                                <LayoutSwitch
                                    value={layout}
                                    onChange={chooseLayout}
                                />
                                <Link className="new" href="/dashboard">
                                    New route
                                </Link>
                            </div>
                            <RouteFilters
                                routes={routes}
                                query={query}
                                sport={sport}
                                surface={surface}
                                hills={hills}
                                roads={roads}
                                onQuery={setQuery}
                                onSport={setSport}
                                onSurface={setSurface}
                                onHills={setHills}
                                onRoads={setRoads}
                            />
                            <div className="dock-list">
                                {visible.length === 0 ? (
                                    <p className="note">
                                        No saved routes match.
                                    </p>
                                ) : (
                                    visible.map((route) => (
                                        <button
                                            key={route.id}
                                            type="button"
                                            className="sheet-card"
                                            aria-current={
                                                openedRoute?.id === route.id
                                                    ? 'true'
                                                    : undefined
                                            }
                                            onClick={() => openRoute(route)}
                                        >
                                            <Thumb
                                                route={route}
                                                image={images[route.id]}
                                            />
                                            <span>
                                                <span className="date">
                                                    {writtenOn(
                                                        route.created_at,
                                                    )}
                                                </span>
                                                <span className="title-line">
                                                    <SportMark
                                                        sport={sportKey(route)}
                                                    />
                                                    <span className="title">
                                                        {route.name}
                                                    </span>
                                                </span>
                                                <span className="stats">
                                                    {kilometres(
                                                        route.distance_metres,
                                                    )}
                                                    {route.ascent_metres > 0
                                                        ? ` · ${route.ascent_metres} m`
                                                        : ''}
                                                </span>
                                                <span className="specs">
                                                    {routeSpecs(route)}
                                                </span>
                                            </span>
                                        </button>
                                    ))
                                )}
                            </div>
                            {openedRoute ? (
                                <div className="dock-detail">
                                    <button
                                        type="button"
                                        className="back"
                                        onClick={() => setOpened(null)}
                                    >
                                        All routes
                                    </button>
                                    <RouteDetail
                                        route={openedRoute}
                                        copied={copied}
                                        onCopy={() => {
                                            void navigator.clipboard
                                                .writeText(openedRoute.url)
                                                .then(() => setCopied(true));
                                        }}
                                    />
                                </div>
                            ) : null}
                        </aside>
                    ) : wide && openedRoute ? (
                        <aside
                            className="side"
                            onClick={dismissIfBlank}
                            onKeyDown={dismissIfBlank}
                        >
                            <button
                                type="button"
                                className="back"
                                onClick={() => setOpened(null)}
                            >
                                All routes
                            </button>
                            <RouteDetail
                                route={openedRoute}
                                copied={copied}
                                onCopy={() => {
                                    void navigator.clipboard
                                        .writeText(openedRoute.url)
                                        .then(() => setCopied(true));
                                }}
                            />
                        </aside>
                    ) : (
                        <section
                            ref={sheetRef}
                            className={`sheet ${sheet}${draggingSheet ? ' dragging' : ''}`}
                            aria-label="Saved routes"
                        >
                            <button
                                type="button"
                                className="grab"
                                onPointerDown={onSheetDown}
                                onPointerMove={onSheetMove}
                                onPointerUp={onSheetUp}
                                onPointerCancel={onSheetUp}
                            >
                                <i />
                                {sheet !== 'detail' ? (
                                    <>
                                        <span className="peek-row">
                                            <b>
                                                {visible.length}{' '}
                                                {visible.length === 1
                                                    ? 'route'
                                                    : 'routes'}
                                            </b>
                                            <span className="aside">
                                                {kilometres(
                                                    visible.reduce(
                                                        (sum, route) =>
                                                            sum +
                                                            route.distance_metres,
                                                        0,
                                                    ),
                                                )}
                                            </span>
                                        </span>
                                        <span className="peek-names">
                                            {visible
                                                .map((route) => route.name)
                                                .join(' · ') ||
                                                'Nothing saved yet'}
                                        </span>
                                    </>
                                ) : null}
                            </button>
                            {sheet === 'detail' && openedRoute ? (
                                <div className="detail">
                                    <button
                                        type="button"
                                        className="back"
                                        onClick={() => setSheet('list')}
                                    >
                                        <svg
                                            width="16"
                                            height="16"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M10 3 L5 8 L10 13"
                                                stroke="currentColor"
                                                strokeWidth="1.6"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                            />
                                        </svg>
                                        All routes
                                    </button>
                                    <RouteDetail
                                        route={openedRoute}
                                        copied={copied}
                                        onCopy={() => {
                                            void navigator.clipboard
                                                .writeText(openedRoute.url)
                                                .then(() => setCopied(true));
                                        }}
                                    />
                                </div>
                            ) : sheet === 'list' || draggingSheet ? (
                                <div className="scroll">
                                    <RouteFilters
                                        routes={routes}
                                        query={query}
                                        sport={sport}
                                        surface={surface}
                                        hills={hills}
                                        roads={roads}
                                        onQuery={setQuery}
                                        onSport={setSport}
                                        onSurface={setSurface}
                                        onHills={setHills}
                                        onRoads={setRoads}
                                    />
                                    {visible.map((route) => (
                                        <button
                                            key={route.id}
                                            type="button"
                                            className="sheet-card"
                                            onClick={() => openRoute(route)}
                                        >
                                            <Thumb
                                                route={route}
                                                image={images[route.id]}
                                            />
                                            <span>
                                                <span className="title-line">
                                                    <SportMark
                                                        sport={sportKey(route)}
                                                    />
                                                    <span className="title">
                                                        {route.name}
                                                    </span>
                                                </span>
                                                <span className="stats">
                                                    {kilometres(
                                                        route.distance_metres,
                                                    )}
                                                    {route.ascent_metres > 0
                                                        ? ` · ${route.ascent_metres} m`
                                                        : ''}
                                                </span>
                                                <span className="specs">
                                                    {routeSpecs(route)}
                                                </span>
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            ) : null}
                        </section>
                    )}
                </div>
            )}
        </div>
    );
}

function choiceChips(
    label: string,
    value: string,
    options: string[],
    names: Record<string, string>,
    onChange: (next: string) => void,
) {
    if (options.length < 2) {
        return null;
    }

    return (
        <div className="chips" role="group" aria-label={label}>
            <button
                type="button"
                aria-pressed={value === 'all'}
                onClick={() => onChange('all')}
            >
                All
            </button>
            {options.map((option) => (
                <button
                    key={option}
                    type="button"
                    aria-pressed={value === option}
                    onClick={() => onChange(option)}
                >
                    {names[option] ?? option}
                </button>
            ))}
        </div>
    );
}

function RouteFilters({
    routes,
    query,
    sport,
    surface,
    hills,
    roads,
    onQuery,
    onSport,
    onSurface,
    onHills,
    onRoads,
}: {
    routes: SavedRoute[];
    query: string;
    sport: string;
    surface: string;
    hills: string;
    roads: string;
    onQuery: (value: string) => void;
    onSport: (value: string) => void;
    onSurface: (value: string) => void;
    onHills: (value: string) => void;
    onRoads: (value: string) => void;
}) {
    return (
        <div className="filters">
            <input
                type="search"
                value={query}
                placeholder="Search routes"
                aria-label="Search routes"
                onChange={(event) => onQuery(event.target.value)}
            />
            {choiceChips(
                'Sport',
                sport,
                [...new Set(routes.map(sportKey))],
                sportLabel,
                onSport,
            )}
            {choiceChips(
                'Surface',
                surface,
                [...new Set(routes.map((route) => route.surface))],
                surfaceLabel,
                onSurface,
            )}
            {choiceChips(
                'Hills',
                hills,
                [...new Set(routes.map((route) => route.elevation))],
                hillLabel,
                onHills,
            )}
            {choiceChips(
                'Roads',
                roads,
                [...new Set(routes.map((route) => route.roads))],
                roadLabel,
                onRoads,
            )}
        </div>
    );
}

function RouteDetail({
    route,
    copied,
    onCopy,
}: {
    route: SavedRoute;
    copied: boolean;
    onCopy: () => void;
}) {
    const [sending, setSending] = useState(false);
    const [details, setDetails] = useState<RouteDetails | null>(null);
    // A multisport plan shows one land leg's climb at a time, counted from 1.
    const landLegs = route.legs.flatMap((leg, index) =>
        leg.discipline === 'swim' ? [] : [index + 1],
    );
    const [legChoice, setLegChoice] = useState<number | null>(null);
    const leg =
        route.legs.length === 0
            ? null
            : legChoice !== null && landLegs.includes(legChoice)
              ? legChoice
              : (landLegs[0] ?? null);

    useEffect(() => {
        setDetails(null);

        if (route.legs.length > 0 && leg === null) {
            return;
        }

        const controller = new AbortController();

        fetch(
            `/routes/saved/${route.id}/details${leg === null ? '' : `?leg=${leg}`}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then((response) => (response.ok ? response.json() : null))
            .then((body: unknown) => {
                const found =
                    typeof body === 'object' &&
                    body !== null &&
                    'details' in body
                        ? body.details
                        : null;
                setDetails(isRouteDetails(found) ? found : null);
            })
            .catch(() => {});

        return () => controller.abort();
    }, [route.id, route.legs.length, leg]);

    const legFits = route.legs.flatMap((leg, index) =>
        leg.fit_url === null
            ? []
            : [{ ...leg, n: index + 1, url: leg.fit_url }],
    );

    return (
        <>
            <div className="detail-head">
                <h2>{route.name}</h2>
                <button
                    type="button"
                    className="icon-btn"
                    aria-label={`Delete ${route.name}`}
                    onClick={() => {
                        router.delete(`/routes/saved/${route.id}`);
                    }}
                >
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 18 18"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M3.5 5h11M7 5V3.5h4V5M5 5l.7 9.2a1 1 0 0 0 1 .8h4.6a1 1 0 0 0 1-.8L13 5"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </button>
            </div>
            <p className="note">{routeSpecs(route)}</p>
            <div className="figures">
                <p>
                    <span>Distance</span>
                    <b>{kilometres(route.distance_metres)}</b>
                </p>
                {route.ascent_metres > 0 ? (
                    <p>
                        <span>Climb</span>
                        <b>{route.ascent_metres} m</b>
                    </p>
                ) : null}
            </div>
            {landLegs.length > 1 ? (
                <div
                    className="tw-details-legs"
                    role="tablist"
                    aria-label="Leg to show"
                >
                    {landLegs.map((number) => (
                        <button
                            key={number}
                            type="button"
                            role="tab"
                            aria-selected={leg === number}
                            onClick={() => setLegChoice(number)}
                        >
                            {`${number} ${sportLabel[route.legs[number - 1]?.discipline ?? ''] ?? ''}`}
                        </button>
                    ))}
                </div>
            ) : null}
            {details === null ? null : (
                <div className="tw-details">
                    <RouteProfile details={details} />
                    <SurfaceBar details={details} />
                </div>
            )}
            <div className="quiet">
                <button type="button" onClick={onCopy}>
                    {copied ? 'Copied' : 'Copy link'}
                </button>
                <a href={route.url}>Open</a>
                <button type="button" onClick={() => setSending(true)}>
                    Send to device
                </button>
            </div>
            <SendToDevice open={sending} onOpenChange={setSending}>
                {legFits.length === 0 ? (
                    <Button asChild>
                        <a href={route.fit_url} download>
                            Download FIT
                        </a>
                    </Button>
                ) : (
                    legFits.map((leg) => (
                        <Button key={leg.n} asChild variant="outline">
                            <a href={leg.url} download>
                                Leg {leg.n},{' '}
                                {sportLabel[leg.discipline] ?? leg.discipline}
                            </a>
                        </Button>
                    ))
                )}
            </SendToDevice>
        </>
    );
}
