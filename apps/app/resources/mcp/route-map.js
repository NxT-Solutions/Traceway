/**
 * The map in the chat. The card in resources/views/mcp/route-map-app.blade.php
 * draws the line first, then loads this from Traceway each time it shows, so a
 * change here reaches the chat without a restart. It lays a map you can move
 * and zoom over the drawing. When the chat app does not allow the map, the
 * drawing stays.
 */
const library = 'https://unpkg.com/maplibre-gl@6.14.0/dist';
const style = 'https://tiles.openfreemap.org/styles/dark';
const legColours = ['#38bdf8', '#f472b6', '#4ade80', '#c4b5fd', '#f8fafc'];
const ink = '#071f20';
const paper = '#f8fafc';
const chartreuse = '#97cb46';
const hub = window.traceway;

function addStyles() {
    const sheet = document.createElement('link');
    sheet.rel = 'stylesheet';
    sheet.href = `${library}/maplibre-gl.css`;

    const own = document.createElement('style');
    own.textContent = `
        .live-map { position: absolute; inset: 0; opacity: 0; transition: opacity 400ms ease; }
        .live-map.ready { opacity: 1; }
        .live-map .maplibregl-ctrl-group { background: ${ink}; border: 1px solid rgba(246, 243, 234, 0.2); }
        .live-map .maplibregl-ctrl-group button + button { border-top-color: rgba(246, 243, 234, 0.2); }
        .live-map .maplibregl-ctrl-icon { filter: invert(1); }
        .live-map .maplibregl-ctrl-attrib { background: rgba(7, 31, 32, 0.7); color: rgba(246, 243, 234, 0.7); }
        .live-map .maplibregl-ctrl-attrib a { color: inherit; }
    `;

    document.head.append(sheet, own);
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
    const start = drawn[0].points[0];

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
            geometry: { type: 'Point', coordinates: start },
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

async function mount(line) {
    const drawn = lines(line);
    const stage = document.querySelector('.stage');

    document.querySelector('.live-map')?.remove();

    if (drawn.length === 0 || stage === null) {
        hub.status('No line came with this plan, so the drawing stays.');

        return;
    }

    let maplibregl;

    try {
        maplibregl = await import(`${library}/maplibre-gl.mjs`);
    } catch (error) {
        hub.status(`The map library did not load: ${error.message}`);

        return;
    }

    const holder = document.createElement('div');
    holder.className = 'live-map';
    stage.append(holder);

    const points = drawn.flatMap(({ points: part }) => part);
    const bounds = points.reduce(
        (box, point) => box.extend(point),
        new maplibregl.LngLatBounds(points[0], points[0]),
    );
    let loaded = false;
    const giveUp = (reason) => {
        if (!loaded) {
            holder.remove();
            hub.status(
                `The map could not draw here, so the drawing stays. ${reason}`,
            );
        }
    };
    const timer = window.setTimeout(() => giveUp('It took too long.'), 12_000);

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
                hub.status('Live map from Traceway.');
            });
        });
    } catch (error) {
        window.clearTimeout(timer);
        giveUp(error.message);
    }
}

if (hub) {
    addStyles();
    hub.status('Live map script loaded.');
    hub.show((_plan, line) => mount(line));
}
