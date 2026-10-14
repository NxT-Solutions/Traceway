<x-mcp::app title="Traceway route">
    <x-slot:head>
        <style>
            :root {
                --ink: #071f20;
                --ink-2: #0b2829;
                --paper: #f6f3ea;
                --muted: rgba(246, 243, 234, 0.66);
                --faint: rgba(246, 243, 234, 0.58);
                --hair: rgba(246, 243, 234, 0.12);
                --edge: rgba(246, 243, 234, 0.22);
                --chart: #97cb46;
                --chart-dim: #86b83c;
                --pad: 20px;
                --font: 'Outfit', var(--font-sans, system-ui), -apple-system, 'Segoe UI', sans-serif;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                background: transparent;
                font-family: var(--font);
                -webkit-font-smoothing: antialiased;
            }

            .card {
                background: var(--ink);
                color: var(--paper);
                border-radius: 16px;
                overflow: hidden;
            }

            .top {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 16px;
                padding: 18px var(--pad) 16px;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 7px;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--muted);
            }

            .brand svg {
                color: var(--paper);
            }

            h1 {
                margin: 8px 0 0;
                font-size: 21px;
                line-height: 1.2;
                font-weight: 600;
                letter-spacing: -0.02em;
                text-wrap: balance;
            }

            .km {
                font-size: 32px;
                line-height: 1;
                font-weight: 600;
                letter-spacing: -0.03em;
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }

            .km small {
                margin-left: 4px;
                font-size: 14px;
                font-weight: 500;
                letter-spacing: 0;
                color: var(--muted);
            }

            .stage {
                position: relative;
                border-block: 1px solid var(--hair);
                background: var(--ink-2);
            }

            .stage.mapped {
                aspect-ratio: 600 / 340;
            }

            .stage>.live-map {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                border: 0;
                opacity: 0;
                transition: opacity 400ms ease;
            }

            .stage>.live-map.ready {
                opacity: 1;
            }

            svg.map {
                display: block;
                width: 100%;
                height: auto;
            }

            .line {
                fill: none;
                stroke-width: 4;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .draw {
                stroke-dasharray: 1;
                stroke-dashoffset: 1;
                animation: draw 1.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            }

            @keyframes draw {
                to {
                    stroke-dashoffset: 0;
                }
            }

            .foot {
                display: flex;
                flex-direction: column;
                gap: 14px;
                padding: 16px var(--pad) var(--pad);
            }

            .facts {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .facts li {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 28px;
                padding: 0 11px;
                border: 1px solid var(--hair);
                border-radius: 999px;
                font-size: 13px;
                font-weight: 500;
                color: var(--muted);
            }

            .facts b {
                color: var(--paper);
                font-weight: 600;
            }

            .facts i {
                width: 9px;
                height: 9px;
                border-radius: 50%;
            }

            .acts {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            button {
                font: inherit;
                font-size: 14px;
                font-weight: 600;
                height: 40px;
                padding: 0 16px;
                border-radius: 10px;
                cursor: pointer;
                border: 1px solid var(--edge);
                background: transparent;
                color: var(--paper);
                transition: background-color 160ms ease, border-color 160ms ease;
            }

            button:hover {
                background: rgba(246, 243, 234, 0.06);
                border-color: rgba(246, 243, 234, 0.36);
            }

            button.fill {
                background: var(--chart);
                border-color: var(--chart);
                color: var(--ink);
            }

            button.fill:hover {
                background: var(--chart-dim);
                border-color: var(--chart-dim);
            }

            button:focus-visible {
                outline: 2px solid var(--chart);
                outline-offset: 2px;
            }

            .note {
                display: flex;
                align-items: center;
                gap: 6px;
                margin: 0;
                font-size: 13px;
                color: var(--faint);
            }

            .note svg {
                color: var(--chart);
            }

            .wait {
                margin: 0;
                padding: 28px var(--pad);
                color: var(--muted);
                font-size: 15px;
            }

            .status {
                margin: 0;
                padding: 0 var(--pad) 12px;
                font-size: 12px;
                color: var(--chart);
            }

            .report {
                margin: 0 var(--pad) 16px;
                max-height: 180px;
                overflow: auto;
                font: 11px/1.45 ui-monospace, Menlo, monospace;
                white-space: pre-wrap;
                word-break: break-all;
                color: var(--faint);
                user-select: text;
            }

            @media (prefers-reduced-motion: reduce) {
                .draw {
                    animation: none;
                    stroke-dashoffset: 0;
                }

                .stage>.live-map {
                    transition: none;
                }
            }

            [hidden] {
                display: none !important;
            }
        </style>

        @verbatim
            <script type="module">
                const legColours = ['#38bdf8', '#f472b6', '#4ade80', '#c4b5fd', '#f8fafc'];
                const sports = {
                    road: 'Road ride',
                    gravel: 'Gravel ride',
                    mtb: 'Mountain-bike ride',
                    run: 'Run',
                    trail_run: 'Trail run',
                    walk: 'Walk',
                    hike: 'Hike',
                    swim: 'Swim',
                };
                const $ = (id) => document.getElementById(id);

                /** A line about the map, on a local site only. */
                function status(text) {
                    if (document.querySelector('main').dataset.status === 'on') {
                        $('status').textContent = text;
                        $('status').hidden = false;
                    }
                }

                /**
                 * On a local site only: what the chat app allows, shown under
                 * the card, so a blocked map says why. The copy sent to
                 * Traceway's log arrives only where the rules allow it.
                 */
                const report = {
                    script: 'not fetched',
                    map: 'not tried',
                    blocked: [],
                    policy: ''
                };

                function sendReport(stage) {
                    const main = document.querySelector('main');

                    if (main.dataset.status !== 'on') {
                        return;
                    }

                    $('report').hidden = false;
                    $('report').textContent = [
                        `Map: ${report.map}`,
                        `Map script: ${report.script}`,
                        `Background worker: ${report.blocked.some((rule) => rule.startsWith('worker-src')) ? 'blocked' : 'allowed'}`,
                        `Blocked: ${report.blocked.join(', ') || 'nothing'}`,
                        `Chat app rules: ${report.policy || 'none reported'}`,
                    ].join('\n');

                    const beacon = new Image();
                    beacon.src = `${main.dataset.traceway}/up?widget=${encodeURIComponent(JSON.stringify({ stage, ...report }))}`;
                }

                document.addEventListener('securitypolicyviolation', (event) => {
                    report.policy = event.originalPolicy;
                    report.blocked.push(`${event.effectiveDirective} ${event.blockedURI}`);
                });

                // A worker the rules block shows up as a blocked worker-src.
                if (document.querySelector('main').dataset.status === 'on') {
                    try {
                        new Worker(URL.createObjectURL(new Blob(['close()'], {
                            type: 'text/javascript'
                        }))).terminate();
                    } catch {
                        report.blocked.push('worker-src blob (threw)');
                    }
                }

                /** A tool result's data, whether it came as structured content or as JSON text. */
                function data(result) {
                    if (result?.structuredContent) {
                        return result.structuredContent;
                    }

                    try {
                        return JSON.parse(result?.content?.[0]?.text ?? '{}');
                    } catch {
                        return {};
                    }
                }

                function say(text) {
                    $('wait').textContent = text;
                    $('wait').hidden = false;
                    $('route').hidden = true;
                }

                /** Fits [lon, lat] lines into the drawing, north up, with the true shape. */
                function project(lines, width, height, pad) {
                    const points = lines.flat();
                    const middle = points.reduce((sum, [, lat]) => sum + lat, 0) / points.length;
                    const squeeze = Math.cos((middle * Math.PI) / 180);
                    const xs = points.map(([lon]) => lon * squeeze);
                    const ys = points.map(([, lat]) => -lat);
                    const [minX, maxX, minY, maxY] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)];
                    const scale = Math.min((width - pad * 2) / (maxX - minX || 1), (height - pad * 2) / (maxY - minY || 1));
                    const offsetX = (width - (maxX - minX) * scale) / 2;
                    const offsetY = (height - (maxY - minY) * scale) / 2;

                    return ([lon, lat]) => [offsetX + (lon * squeeze - minX) * scale, offsetY + (-lat - minY) * scale];
                }

                function path(line, toPoint) {
                    return line.map((point, index) => {
                        const [x, y] = toPoint(point);

                        return `${index === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
                    }).join(' ');
                }

                const surfaces = {
                    paved: 'Paved',
                    mixed: 'Mixed surface',
                    dirt: 'Dirt and trail',
                };

                /** The facts under the map: the sport or the legs, the climb, the ground, the stops. */
                function facts(route, line) {
                    const legs = route.legs ?? [];
                    const climb = line?.ascent_metres ?? 0;
                    const stops = line?.stops?.length ?? 0;
                    const shown = legs.length === 0 ? [`<li><b>${sports[route.sport] ?? route.sport ?? 'Route'}</b></li>`] :
                        legs.map((leg) => {
                            const land = leg.discipline !== 'swim';
                            const colour = land ? legColours[legs.filter((other) => other.discipline !== 'swim').indexOf(leg) %
                                legColours.length] : 'rgba(246, 243, 234, 0.4)';
                            const km = land ? `${Number(leg.distance_km).toFixed(1)} km` : 'draw it in the planner';

                            return `<li><i style="background:${colour}"></i><b>${sports[leg.discipline] ?? leg.discipline}</b> ${km}</li>`;
                        });

                    shown.push(climb > 0 ? `<li><b>${climb} m</b> climb</li>` : '<li>Flat</li>');

                    if (surfaces[route.surface]) {
                        shown.push(`<li>${surfaces[route.surface]}</li>`);
                    }

                    if (stops > 0) {
                        shown.push(`<li><b>${stops}</b> ${stops === 1 ? 'stop' : 'stops'} on the way</li>`);
                    }

                    return shown;
                }

                /** The card: the name, the distance, the facts, and the line when there is one. */
                function draw(route, line) {
                    const legs = route.legs ?? [];
                    const landLegs = (line?.legs ?? []).filter((leg) => leg.discipline !== 'swim' && leg.line.length > 1);
                    const lines = landLegs.length > 0 ? landLegs.map((leg) => leg.line) : (line?.line?.length > 1 ? [line.line] :
                    []);
                    const map = $('map');

                    if (lines.length > 0) {
                        const toPoint = project(lines, 600, 340, 28);
                        const start = toPoint(lines[0][0]);

                        map.innerHTML = lines.map((points, index) =>
                                `<path class="line draw" pathLength="1" style="animation-delay:${index * 0.35}s" stroke="${landLegs.length > 0 ? legColours[index % legColours.length] : '#f8fafc'}" d="${path(points, toPoint)}"></path>`
                            ).join('') +
                            `<circle cx="${start[0].toFixed(1)}" cy="${start[1].toFixed(1)}" r="8" fill="#97cb46" stroke="#f6f3ea" stroke-width="3"></circle>`;
                    }

                    map.toggleAttribute('hidden', lines.length === 0);
                    $('name').textContent = route.name;
                    $('km').querySelector('span').textContent = Number(route.distance_km ?? (route.distance_metres ?? 0) / 1000)
                        .toFixed(1);
                    $('legs').innerHTML = facts(route, line).join('');

                    $('wait').hidden = true;
                    $('route').hidden = false;
                }

                /** Gives up on a call the host never answers, so the card does not wait forever. */
                function within(promise, milliseconds) {
                    return Promise.race([
                        promise,
                        new Promise((resolve, reject) => setTimeout(() => reject(new Error('No answer')), milliseconds)),
                    ]);
                }

                /**
                 * The line, sent in the plan's structured content. A chat app that
                 * leaves it out gets one try at asking the server, and the card
                 * shows without a line when that fails too.
                 */
                async function lineFor(app, result, plan) {
                    const sent = plan.drawing;

                    if (sent?.line) {
                        return sent;
                    }

                    try {
                        const drawing = await within(app.callServerTool('route_drawing', {
                            id: plan.id
                        }), 8000);

                        return drawing?.isError ? null : data(drawing);
                    } catch {
                        return null;
                    }
                }

                /**
                 * Traceway's own share page over the drawing, where the chat app
                 * allows a frame. Resolves true once it says its map is drawn.
                 */
                function framed(plan, stage) {
                    return new Promise((resolve) => {
                        if (!plan.embed_url) {
                            resolve(false);

                            return;
                        }

                        const frame = document.createElement('iframe');
                        frame.className = 'live-map';
                        frame.title = 'The route on a map you can move and zoom';
                        const settle = (shown) => {
                            window.clearTimeout(timer);
                            window.removeEventListener('message', ready);
                            document.removeEventListener('securitypolicyviolation', refused);

                            if (!shown) {
                                frame.remove();
                            }

                            resolve(shown);
                        };
                        const timer = window.setTimeout(() => settle(false), 15000);
                        const ready = (event) => {
                            if (event.source === frame.contentWindow && event.data?.type === 'traceway:map-ready') {
                                frame.classList.add('ready');
                                settle(true);
                            }
                        };
                        const refused = (event) => {
                            if (event.effectiveDirective === 'frame-src') {
                                settle(false);
                            }
                        };

                        window.addEventListener('message', ready);
                        document.addEventListener('securitypolicyviolation', refused);
                        frame.src = plan.embed_url;
                        stage.append(frame);
                    });
                }

                /**
                 * The map script, fresh from Traceway so it is never older than
                 * the site. Null when Traceway cannot be reached from here.
                 */
                async function freshCode() {
                    try {
                        const response = await fetch(document.querySelector('main').dataset.mapScript, {
                            cache: 'no-cache'
                        });

                        return response.ok ? await response.text() : null;
                    } catch {
                        return null;
                    }
                }

                /** The copy of the map script this card came with. */
                function cardCode() {
                    const bytes = Uint8Array.from(atob(document.querySelector('main').dataset.mapCode), (character) =>
                        character.charCodeAt(0));

                    return new TextDecoder().decode(bytes);
                }

                /**
                 * Runs a map script. Resolves true when its map shows, false
                 * when the chat app kept it from drawing, and throws when the
                 * script itself is broken.
                 */
                async function runMap(code, line, stage) {
                    const map = await import(URL.createObjectURL(new Blob([code], {
                        type: 'text/javascript'
                    })));

                    return map.mount({
                        line,
                        stage,
                        status,
                        files: `${document.querySelector('main').dataset.traceway}/mcp/apps/files`,
                    });
                }

                /**
                 * A map you can move and zoom over the drawing: Traceway's page
                 * where the chat app allows a frame, else the map script run in
                 * the card. When neither works, the drawing stays.
                 */
                async function showMap(plan, line) {
                    const stage = document.querySelector('.stage');

                    stage?.querySelector('.live-map')?.remove();

                    if (stage === null) {
                        return;
                    }

                    let shown = await framed(plan, stage);
                    report.map = shown ? 'Traceway page in a frame' : 'no frame';

                    // The fresh script first. A broken one, an error page served
                    // as a script, or one that no longer fits this card, falls
                    // back to the copy the card came with.
                    for (const [source, code] of shown ? [] : [
                            ['fresh from Traceway', await freshCode()],
                            ['copy in the card', cardCode()],
                        ]) {
                        if (code === null) {
                            continue;
                        }

                        try {
                            shown = await runMap(code, line, stage);
                            report.script = source;
                            report.map = shown ? 'map script in the card' : 'map script could not draw';

                            break;
                        } catch (error) {
                            report.script = `${source} failed (${error.message})`;
                        }
                    }

                    if (shown) {
                        stage.classList.add('mapped');
                        status('Map you can move and zoom.');
                    } else {
                        status('No map here, so the drawing stays.');
                    }

                    sendReport(shown ? 'map shown' : 'no map');
                }

                createMcpApp(async (app) => {
                    let links = {};

                    app.autoResize();

                    for (const [button, key] of [
                            ['open', 'map_url'],
                            ['garmin', 'garmin_url'],
                            ['gpx', 'gpx_url']
                        ]) {
                        $(button).addEventListener('click', () => links[key] && app.openLink(links[key]));
                    }

                    app.onToolInput((params) => {
                        const input = params?.arguments ?? {};
                        const what = input.event ? `${input.race ?? ''} ${input.event}`.trim() :
                            `${input.distance_km ?? ''} km ${sports[input.sport] ?? 'loop'}`.trim();

                        say(`Planning ${what.toLowerCase()}${input.start ? ` from ${input.start}` : ''}…`);
                    });

                    app.onToolResult(async (params) => {
                        const result = params?.result ?? params;

                        if (result?.isError) {
                            say(result.content?.[0]?.text ?? 'No route this time.');
                            return;
                        }

                        const plan = data(result);

                        if (!plan.id) {
                            say('No route to draw.');
                            return;
                        }

                        links = plan;
                        const line = await lineFor(app, result, plan);
                        draw(plan, line);
                        showMap(plan, line);
                    });
                });
            </script>
        @endverbatim
    </x-slot:head>

    <main class="card" data-status="{{ $showStatus ? 'on' : 'off' }}" data-traceway="{{ $traceway }}"
        data-map-script="{{ $mapScript }}" data-map-code="{{ base64_encode($mapCode) }}">
        <p id="wait" class="wait">Planning your route…</p>

        <section id="route" hidden>
            <header class="top">
                <div>
                    <div class="brand">
                        <svg viewBox="0 0 32 32" width="16" height="16" fill="none" aria-hidden="true">
                            <path d="M8.2 25.2 V15.4 a5.1 5.1 0 0 1 5.1 -5.1 h3.4 a5.1 5.1 0 0 1 5.1 5.1 v2.3"
                                stroke="currentColor" stroke-width="2.7" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <circle cx="22.1" cy="23.5" r="2.45" fill="#97cb46" />
                        </svg>
                        Traceway
                    </div>
                    <h1 id="name"></h1>
                </div>
                <div id="km" class="km"><span></span><small>km</small></div>
            </header>

            <div class="stage">
                <svg id="map" class="map" viewBox="0 0 600 340" role="img"
                    aria-label="The route on a map"></svg>
            </div>

            <div class="foot">
                <ul id="legs" class="facts"></ul>

                <div class="acts">
                    <button id="open" class="fill" type="button">Open the map</button>
                    <button id="garmin" type="button">Garmin file</button>
                    <button id="gpx" type="button">GPX file</button>
                </div>

                <p class="note">
                    <svg viewBox="0 0 16 16" width="14" height="14" fill="none" aria-hidden="true">
                        <path d="M3.5 8.5 6.5 11.5 12.5 4.5" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Saved to your Traceway library.
                </p>
            </div>
        </section>

        <p id="status" class="status" hidden></p>
        <pre id="report" class="report" hidden></pre>
    </main>
</x-mcp::app>
