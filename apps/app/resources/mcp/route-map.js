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
const legColours = ['#38bdf8', '#f472b6', '#4ade80', '#c4b5fd', '#f8fafc'];
const ink = '#071f20';
const paper = '#f8fafc';
const chartreuse = '#97cb46';

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
            .live-map .maplibregl-ctrl-group { background: ${ink}; border: 1px solid rgba(246, 243, 234, 0.2); }
            .live-map .maplibregl-ctrl-group button + button { border-top-color: rgba(246, 243, 234, 0.2); }
            .live-map .maplibregl-ctrl-icon { filter: invert(1); }
            .live-map .maplibregl-ctrl-attrib { background: rgba(7, 31, 32, 0.7); color: rgba(246, 243, 234, 0.7); }
            .live-map .maplibregl-ctrl-attrib a { color: inherit; }`;
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

function addRoute(map, drawn) {
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
    map.addSource('start', {
        type: 'geojson',
        data: {
            type: 'Feature',
            properties: {},
            geometry: { type: 'Point', coordinates: drawn[0].points[0] },
        },
    });
    map.addLayer({
        id: 'route-casing',
        type: 'line',
        source: 'route',
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': ink, 'line-width': 8 },
    });
    map.addLayer({
        id: 'route-line',
        type: 'line',
        source: 'route',
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': ['get', 'colour'], 'line-width': 4 },
    });
    map.addLayer({
        id: 'start',
        type: 'circle',
        source: 'start',
        paint: {
            'circle-radius': 8,
            'circle-color': chartreuse,
            'circle-stroke-color': paper,
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
                fitBoundsOptions: { padding: 32 },
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
            map.on('load', () => {
                loaded = true;
                window.clearTimeout(timer);
                addRoute(map, drawn);
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
