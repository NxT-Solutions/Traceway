/**
 * The map in the chat card. The card in
 * resources/views/mcp/route-map-app.blade.php fetches this from Traceway each
 * time it shows, so a change here reaches the chat without a restart, and
 * keeps the copy it was sent with for when Traceway cannot be reached.
 *
 * Chat apps let a card run code only from blob: addresses, so MapLibre and
 * its worker are fetched as text and run from there.
 */
const maplibreDist = 'https://unpkg.com/maplibre-gl@6.14.0/dist';
const style = 'https://tiles.openfreemap.org/styles/dark';
const fonts = 'https://fonts.bunny.net/outfit/files';
const legColours = ['#38bdf8', '#f472b6', '#4ade80', '#c4b5fd', '#f6f3ea'];
const ink = '#071f20';
const paper = '#f6f3ea';
const chartreuse = '#97cb46';
const stopColours = { water: '#1d4ed8', toilet: '#6d28d9', cafe: '#0f766e' };

/**
 * OpenFreeMap's dark map in Traceway's ink: teal water, green woods, quiet
 * roads, and paper labels, so the map sits in the card as one piece.
 */
const brandPaint = {
    background: { 'background-color': '#0a2526' },
    water: { 'fill-color': '#0f3a3f' },
    waterway: { 'line-color': '#0f3a3f' },
    landcover_wood: { 'fill-color': '#0d2e2a' },
    landuse_park: { 'fill-color': '#0d2e2a' },
    landuse_residential: { 'fill-color': '#0c2829' },
    building: { 'fill-color': '#0c2324', 'fill-outline-color': '#133234' },
    highway_path: { 'line-color': '#163536' },
    highway_minor: { 'line-color': '#1a3a3b' },
    highway_major_casing: { 'line-color': 'rgba(246, 243, 234, 0.08)' },
    highway_major_inner: { 'line-color': '#21444a' },
    highway_major_subtle: { 'line-color': '#21444a' },
    highway_motorway_casing: { 'line-color': 'rgba(246, 243, 234, 0.1)' },
    highway_motorway_inner: { 'line-color': '#2a5157' },
    highway_motorway_subtle: { 'line-color': '#21444a' },
    railway: { 'line-color': '#1c3b3c' },
    railway_minor: { 'line-color': '#1c3b3c' },
    railway_transit: { 'line-color': '#1c3b3c' },
    boundary_state: { 'line-color': '#2f4f50' },
    'boundary_country_z5-': { 'line-color': '#2f4f50' },
    water_name: { 'text-color': '#6f9ea2', 'text-halo-color': ink },
    highway_name_other: { 'text-color': '#6d8a88', 'text-halo-color': ink },
    highway_name_motorway: { 'text-color': '#6d8a88' },
    place_village: {
        'text-color': 'rgba(246, 243, 234, 0.6)',
        'text-halo-color': 'rgba(7, 31, 32, 0.9)',
    },
    place_suburb: {
        'text-color': 'rgba(246, 243, 234, 0.6)',
        'text-halo-color': 'rgba(7, 31, 32, 0.9)',
    },
    place_other: {
        'text-color': 'rgba(246, 243, 234, 0.5)',
        'text-halo-color': 'rgba(7, 31, 32, 0.9)',
    },
    place_town: {
        'text-color': 'rgba(246, 243, 234, 0.8)',
        'text-halo-color': 'rgba(7, 31, 32, 0.9)',
    },
    place_city: {
        'text-color': 'rgba(246, 243, 234, 0.86)',
        'text-halo-color': 'rgba(7, 31, 32, 0.9)',
    },
    place_city_large: {
        'text-color': 'rgba(246, 243, 234, 0.9)',
        'text-halo-color': 'rgba(7, 31, 32, 0.9)',
    },
};

async function text(url) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`${url} answered ${response.status}`);
    }

    return response.text();
}

function blob(code) {
    return URL.createObjectURL(new Blob([code], { type: 'text/javascript' }));
}

/** Outfit, the Traceway font, for the card. The system font stands in until then. */
async function brandFont() {
    try {
        const faces = await Promise.all(
            ['400', '600'].map(async (weight) => {
                const response = await fetch(
                    `${fonts}/outfit-latin-${weight}-normal.woff2`,
                );
                const face = new FontFace(
                    'Outfit',
                    await response.arrayBuffer(),
                    { weight },
                );

                return face.load();
            }),
        );

        for (const face of faces) {
            document.fonts.add(face);
        }
    } catch {
        // The system font is fine.
    }
}

let loading = null;

/** MapLibre, its worker, and its styles, loaded once per card. */
function maplibre() {
    loading ??= (async () => {
        const [main, worker, css] = await Promise.all([
            text(`${maplibreDist}/maplibre-gl.mjs`),
            text(`${maplibreDist}/maplibre-gl-worker.mjs`),
            text(`${maplibreDist}/maplibre-gl.css`),
        ]);
        const sheet = document.createElement('style');
        sheet.textContent = `${css}
            .stage > .live-map.maplibregl-map { position: absolute; }
            .live-map .maplibregl-ctrl-top-right { margin: 12px 12px 0 0; }
            .live-map .maplibregl-ctrl-top-right .maplibregl-ctrl { margin: 0; }
            .live-map .maplibregl-ctrl-group { background: rgba(7, 31, 32, 0.88); border: 1px solid rgba(246, 243, 234, 0.18); border-radius: 10px; box-shadow: none; overflow: hidden; }
            .live-map .maplibregl-ctrl-group button { width: 32px; height: 32px; }
            .live-map .maplibregl-ctrl-group button + button { border-top: 1px solid rgba(246, 243, 234, 0.14); }
            .live-map .maplibregl-ctrl-group button:hover { background: rgba(246, 243, 234, 0.08); }
            .live-map .maplibregl-ctrl-icon { filter: invert(1) brightness(1.4); }
            .live-map .maplibregl-ctrl-bottom-right { margin: 0 8px 8px 0; }
            .live-map .maplibregl-ctrl-attrib { background: rgba(7, 31, 32, 0.82); color: rgba(246, 243, 234, 0.66); border-radius: 999px; font: 11px/1.4 Outfit, system-ui, sans-serif; }
            .live-map .maplibregl-ctrl-attrib.maplibregl-compact-show { padding: 3px 28px 3px 10px; }
            .live-map .maplibregl-ctrl-attrib-button { background-color: transparent; filter: invert(1); }
            .live-map .maplibregl-ctrl-attrib a { color: inherit; }
            .live-map .maplibregl-cooperative-gesture-screen { background: rgba(7, 31, 32, 0.78); color: ${paper}; font: 600 15px/1.4 Outfit, system-ui, sans-serif; }`;
        document.head.append(sheet);

        const maplibregl = await import(blob(main));
        maplibregl.setWorkerUrl(blob(worker));

        return maplibregl;
    })();

    return loading;
}

/** Each land leg in its own colour, or the one line of a single ride. */
function lines(line) {
    const legs = (line?.legs ?? []).filter(
        (leg) => leg.discipline !== 'swim' && leg.line.length > 1,
    );

    if (legs.length > 0) {
        return legs.map((leg, index) => ({
            points: leg.line,
            colour: legColours[index % legColours.length],
        }));
    }

    return line?.line?.length > 1 ? [{ points: line.line, colour: paper }] : [];
}

function canvas(size, draw) {
    const node = document.createElement('canvas');
    node.width = size;
    node.height = size;
    const context = node.getContext('2d');
    draw(context, size);

    return context.getImageData(0, 0, size, size);
}

/** A small ink chevron that rides the route and points the way. */
function arrowImage() {
    return canvas(28, (context) => {
        context.strokeStyle = ink;
        context.lineWidth = 3.4;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.beginPath();
        context.moveTo(10, 7);
        context.lineTo(18, 14);
        context.lineTo(10, 21);
        context.stroke();
    });
}

/** The share page's stop pins: water, toilets, and cafés. */
function stopImage(kind) {
    return canvas(44, (context, size) => {
        context.fillStyle = paper;
        context.beginPath();
        context.arc(size / 2, size / 2, size / 2 - 1, 0, Math.PI * 2);
        context.fill();
        context.fillStyle = stopColours[kind];
        context.beginPath();
        context.arc(size / 2, size / 2, size / 2 - 5, 0, Math.PI * 2);
        context.fill();
        context.fillStyle = '#ffffff';
        context.strokeStyle = '#ffffff';
        context.lineWidth = 2.5;

        if (kind === 'water') {
            context.beginPath();
            context.moveTo(22, 11);
            context.bezierCurveTo(22, 11, 13, 21, 13, 26);
            context.arc(22, 26, 9, Math.PI, 0, true);
            context.bezierCurveTo(31, 21, 22, 11, 22, 11);
            context.fill();
        } else if (kind === 'cafe') {
            context.fillRect(12, 17, 15, 12);
            context.beginPath();
            context.arc(29, 23, 4, -Math.PI / 2, Math.PI / 2);
            context.stroke();
            context.fillRect(11, 31, 18, 2.5);
        } else {
            context.font = 'bold 15px system-ui, sans-serif';
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText('WC', size / 2, size / 2 + 1);
        }
    });
}

function brand(map) {
    for (const [id, paint] of Object.entries(brandPaint)) {
        if (map.getLayer(id) === undefined) {
            continue;
        }

        for (const [property, value] of Object.entries(paint)) {
            try {
                map.setPaintProperty(id, property, value);
            } catch {
                // A layer this style draws differently keeps its own colour.
            }
        }
    }
}

function point(coordinates, properties = {}) {
    return {
        type: 'Feature',
        properties,
        geometry: { type: 'Point', coordinates },
    };
}

/** Metres between two points, near enough to tell a loop from a line. */
function apart([a, b], [c, d]) {
    const x = (c - a) * Math.cos((((b + d) / 2) * Math.PI) / 180);

    return Math.hypot(x, d - b) * 111_320;
}

function addRoute(map, drawn, stops) {
    const start = drawn[0].points[0];
    const lastLine = drawn[drawn.length - 1].points;
    const finish = lastLine[lastLine.length - 1];

    map.addImage('route-arrow', arrowImage(), { pixelRatio: 2 });

    for (const kind of Object.keys(stopColours)) {
        map.addImage(`stop-${kind}`, stopImage(kind), { pixelRatio: 2 });
    }

    map.addSource('route', {
        type: 'geojson',
        data: {
            type: 'FeatureCollection',
            features: drawn.map(({ points, colour }) => ({
                type: 'Feature',
                properties: { colour },
                geometry: { type: 'LineString', coordinates: points },
            })),
        },
    });
    map.addSource('ends', {
        type: 'geojson',
        data: {
            type: 'FeatureCollection',
            features: [
                point(start, { end: 'start' }),
                ...(apart(start, finish) > 150
                    ? [point(finish, { end: 'finish' })]
                    : []),
            ],
        },
    });
    map.addSource('stops', {
        type: 'geojson',
        data: {
            type: 'FeatureCollection',
            features: stops
                .filter((stop) => stopColours[stop.kind])
                .map((stop) =>
                    point([stop.longitude, stop.latitude], {
                        icon: `stop-${stop.kind}`,
                    }),
                ),
        },
    });
    map.addLayer({
        id: 'route-casing',
        type: 'line',
        source: 'route',
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': ink, 'line-width': 9, 'line-opacity': 0.9 },
    });
    map.addLayer({
        id: 'route-line',
        type: 'line',
        source: 'route',
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': ['get', 'colour'], 'line-width': 4.5 },
    });
    map.addLayer({
        id: 'route-arrows',
        type: 'symbol',
        source: 'route',
        layout: {
            'symbol-placement': 'line',
            'symbol-spacing': 80,
            'icon-image': 'route-arrow',
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'icon-rotation-alignment': 'map',
            'icon-keep-upright': false,
        },
    });
    map.addLayer({
        id: 'stops',
        type: 'symbol',
        source: 'stops',
        layout: {
            'icon-image': ['get', 'icon'],
            'icon-allow-overlap': true,
            'icon-size': 0.9,
        },
    });
    map.addLayer({
        id: 'ends-halo',
        type: 'circle',
        source: 'ends',
        filter: ['==', ['get', 'end'], 'start'],
        paint: {
            'circle-radius': 13,
            'circle-color': chartreuse,
            'circle-opacity': 0.22,
        },
    });
    map.addLayer({
        id: 'ends',
        type: 'circle',
        source: 'ends',
        paint: {
            'circle-radius': ['match', ['get', 'end'], 'start', 7.5, 6],
            'circle-color': [
                'match',
                ['get', 'end'],
                'start',
                chartreuse,
                paper,
            ],
            'circle-stroke-color': ink,
            'circle-stroke-width': 3,
        },
    });
}

/**
 * Lays a map you can move and zoom over the drawing in the stage. Resolves
 * true once it shows, false when it could not, and leaves the drawing then.
 */
export async function mount({ line, stage, status }) {
    const drawn = lines(line);

    void brandFont();

    stage.querySelector('.live-map')?.remove();

    if (drawn.length === 0) {
        status('No line came with this plan, so the drawing stays.');

        return false;
    }

    let maplibregl;

    try {
        maplibregl = await maplibre();
    } catch (error) {
        status(`The map library did not load: ${error.message}`);

        return false;
    }

    const holder = document.createElement('div');
    holder.className = 'live-map';
    stage.append(holder);

    const points = drawn.flatMap(({ points: part }) => part);
    const bounds = points.reduce(
        (box, point) => box.extend(point),
        new maplibregl.LngLatBounds(points[0], points[0]),
    );

    return new Promise((resolve) => {
        let loaded = false;
        const giveUp = (reason) => {
            if (!loaded) {
                loaded = true;
                holder.remove();
                status(
                    `The map could not draw here, so the drawing stays. ${reason}`,
                );
                resolve(false);
            }
        };
        const timer = window.setTimeout(
            () => giveUp('It took too long.'),
            15_000,
        );

        try {
            const map = new maplibregl.Map({
                container: holder,
                style,
                bounds,
                fitBoundsOptions: {
                    padding: { top: 36, bottom: 36, left: 36, right: 60 },
                },
                maxZoom: 17,
                cooperativeGestures: true,
                dragRotate: false,
                pitchWithRotate: false,
                touchPitch: false,
                attributionControl: { compact: true },
            });

            map.addControl(
                new maplibregl.NavigationControl({ showCompass: false }),
                'top-right',
            );
            map.on('error', (event) => giveUp(event?.error?.message ?? ''));
            map.on('style.load', () => brand(map));
            map.on('load', () => {
                loaded = true;
                window.clearTimeout(timer);
                addRoute(map, drawn, line?.stops ?? []);
                map.once('idle', () => {
                    holder.classList.add('ready');
                    resolve(true);
                });
            });
        } catch (error) {
            window.clearTimeout(timer);
            giveUp(error.message);
        }
    });
}
