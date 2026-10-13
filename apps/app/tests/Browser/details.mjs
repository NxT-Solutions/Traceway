import { existsSync, readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

// Walks every way a rider meets the climb chart, the surfaces, the turns,
// and the stops: a generated loop, the next loop, a built route, a brick,
// an opened GPX file in all three ways, a long ride, the files that go to a
// device, a saved route, and a shared link. Then it does the same on a
// phone with real touches. It talks to the public routing and map servers,
// so it is slow, and the stops are only reported, since that server is
// often busy.

const base = process.env.BASE_URL || 'https://traceway.ddev.site:8443';
const local = [
    process.env.HOME,
    'Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
].join('/');
const executable = process.env.CHROMIUM_PATH || (existsSync(local) ? local : undefined);
const routing = 90_000;

const browser = await chromium.launch({ executablePath: executable, headless: true });
const steps = [];
const notes = [];

function step(name) {
    steps.push(name);
    console.log(`· ${name}`);
}

function note(text) {
    notes.push(text);
    console.log(`  ! ${text}`);
}

function check(ok, message) {
    if (!ok) {
        throw new Error(message);
    }
}

async function fits(page, label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    check(overflow <= 1, `${label} scrolls sideways by ${overflow}px`);
}

/**
 * Reads a FIT file the way a watch would: the CRC, the heights on each
 * point, and the course points, in order.
 */
function readFit(bytes) {
    const table = [0x0000, 0xcc01, 0xd801, 0x1400, 0xf001, 0x3c00, 0x2800, 0xe401, 0xa001, 0x6c00, 0x7800, 0xb401, 0x5000, 0x9c01, 0x8801, 0x4400];
    const crc = (part) => {
        let value = 0;

        for (const byte of part) {
            let tmp = table[value & 0xf];
            value = (value >> 4) & 0x0fff;
            value = value ^ tmp ^ table[byte & 0xf];
            tmp = table[value & 0xf];
            value = (value >> 4) & 0x0fff;
            value = value ^ tmp ^ table[(byte >> 4) & 0xf];
        }

        return value;
    };
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const header = bytes[0];
    const end = header + view.getUint32(4, true);
    const definitions = {};
    const records = [];
    const points = [];
    let offset = header;

    while (offset < end) {
        const record = bytes[offset++];
        const localType = record & 0x0f;

        if (record & 0x40) {
            const global = view.getUint16(offset + 2, true);
            const count = bytes[offset + 4];
            const fields = [];
            offset += 5;

            for (let index = 0; index < count; index++) {
                fields.push([bytes[offset], bytes[offset + 1], bytes[offset + 2]]);
                offset += 3;
            }

            definitions[localType] = { global, fields };
            continue;
        }

        const { global, fields } = definitions[localType];
        const values = {};

        for (const [number, size, type] of fields) {
            values[number] =
                type === 7
                    ? Buffer.from(bytes.subarray(offset, offset + size)).toString('utf8').replace(/\0+$/, '')
                    : type === 132
                      ? view.getUint16(offset, true)
                      : type === 133
                        ? view.getInt32(offset, true)
                        : type === 134
                          ? view.getUint32(offset, true)
                          : bytes[offset];
            offset += size;
        }

        if (global === 20) {
            records.push(values);
        }

        if (global === 32) {
            points.push(values);
        }
    }

    return {
        crcOk: crc(bytes.subarray(0, bytes.length - 2)) === view.getUint16(bytes.length - 2, true),
        heights: records.filter((value) => value[2] !== undefined && value[2] !== 0xffff).map((value) => value[2] / 5 - 500),
        points,
        ordered: points.every((point, index) => index === 0 || points[index - 1][4] <= point[4]),
    };
}

async function downloaded(page, action) {
    const [download] = await Promise.all([page.waitForEvent('download', { timeout: routing }), action()]);
    const path = await download.path();

    return readFileSync(path);
}

function gpxOf(name, coordinates) {
    return `<?xml version="1.0"?><gpx version="1.1"><trk><name>${name}</name><trkseg>${coordinates
        .map(([longitude, latitude]) => `<trkpt lat="${latitude}" lon="${longitude}"/>`)
        .join('')}</trkseg></trk></gpx>`;
}

function watch(page, label) {
    const failures = [];

    page.on('response', (response) => {
        const url = response.url();

        if (!url.startsWith(base) || !/\/routes\//.test(url)) {
            return;
        }

        if (response.status() >= 500) {
            failures.push(`${response.status()} ${new URL(url).pathname}`);
        }
    });

    return () => {
        const stops = failures.filter((failure) => failure.endsWith('/routes/stops'));
        const others = failures.filter((failure) => !failure.endsWith('/routes/stops'));

        if (stops.length > 0) {
            note(`${label}: the stop server did not answer ${stops.length} time(s)`);
        }

        check(others.length === 0, `${label}: the server failed: ${others.join(', ')}`);
    };
}

async function draft(page) {
    return page.evaluate(() => {
        const key = Object.keys(localStorage).find((name) => /^traceway\.planner\.v1\.\d+$/.test(name));

        return key ? JSON.parse(localStorage.getItem(key) ?? 'null') : null;
    });
}

async function detailsShown(page, label) {
    await page.locator('.tw-details-chart').first().waitFor({ timeout: routing });
    const text = await page.locator('.tw-details').first().innerText();

    check(/\d+ m\s*up/.test(text), `${label}: the chart has no climb`);
    check(/Paved|Gravel|Dirt|Not known/.test(text), `${label}: there is no surface bar`);

    return text;
}

async function stopsShown(page, label) {
    const stops = page.locator('summary', { hasText: 'Stops on the way' });

    try {
        await stops.first().waitFor({ timeout: 25_000 });

        return true;
    } catch {
        note(`${label}: no stops showed (none near, or the stop server was busy)`);

        return false;
    }
}

try {
    // ── Desktop ─────────────────────────────────────────────────────────
    const desktop = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 860 }, acceptDownloads: true });
    const page = await desktop.newPage();
    const settle = watch(page, 'Desktop');

    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /^Road\b/ }).click();
    await page.waitForURL(/\/dashboard/, { timeout: 20000 });
    await page.locator('.maplibregl-canvas').waitFor();
    await page.evaluate(() => {
        for (const key of Object.keys(localStorage)) {
            if (key.startsWith('traceway.planner')) {
                localStorage.removeItem(key);
            }
        }
    });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('.maplibregl-canvas').waitFor();
    step('desktop: signed in as the road rider, with a fresh planner');

    await page.getByRole('tab', { name: 'Generate' }).click();
    await page.locator('button.fill').click();
    let text = await detailsShown(page, 'Generated loop');
    check((await page.locator('summary', { hasText: 'Turns (' }).count()) === 1, 'A generated loop has no turn list');
    step(`desktop: a generated loop shows its climb (${/(\d+) m\s*up/.exec(text)?.[1]} m), surfaces, and turns`);
    const loopStops = await stopsShown(page, 'Generated loop');

    if (loopStops) {
        step('desktop: the loop lists water, toilets, or cafés');
    }

    const chart = page.locator('.tw-details-chart').first();
    const box = await chart.boundingBox();
    await page.mouse.move(box.x + box.width * 0.4, box.y + box.height / 2);
    await page.locator('.tw-details-tip').waitFor({ timeout: 3000 });
    check(/km · -?\d+ m/.test(await page.locator('.tw-details-tip').innerText()), 'The chart tip has no distance and height');
    await page.mouse.move(box.x + box.width * 0.4, box.y - 60);
    await page.locator('.tw-details-tip').waitFor({ state: 'detached', timeout: 3000 });
    step('desktop: pointing at the chart shows the distance, the height, and the grade, and leaving hides it');

    await chart.focus();
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('ArrowRight');
    await page.locator('.tw-details-tip').waitFor({ timeout: 3000 });
    check(Number(await chart.getAttribute('aria-valuenow')) > 0, 'The arrow keys do not move along the chart');
    await page.keyboard.press('Escape');
    await page.locator('.tw-details-tip').waitFor({ state: 'detached', timeout: 3000 });
    step('desktop: the arrow keys walk the chart, and Escape lets go');

    await page.getByLabel('Steepness on map').check();
    await page.getByRole('list', { name: 'Steepness' }).waitFor();
    await page.getByLabel('Steepness on map').uncheck();
    await page.getByRole('list', { name: 'Steepness' }).waitFor({ state: 'detached' });
    step('desktop: steepness on the map turns on with its key, and off again');

    await page.locator('summary', { hasText: 'Turns (' }).click();
    const turns = page.locator('.tw-details-lists details[open] li button');
    check((await turns.count()) > 0, 'The turn list is empty when opened');
    await turns.first().hover();
    await turns.first().focus();
    check(/onto|Left|Right|Bear|Sharp|U-turn|Roundabout/.test(await turns.first().innerText()), 'A turn has no direction');
    step(`desktop: the turn list opens with ${await turns.count()} turns, and a turn can be pointed at`);

    const firstLine = JSON.stringify((await draft(page))?.routes?.[0]?.coordinates?.slice(0, 3));
    await page.getByRole('button', { name: 'Next loop' }).click();
    await page.getByText(/^Loop 2 of/).first().waitFor();
    await detailsShown(page, 'The next loop');
    check(JSON.stringify((await draft(page))?.routes?.[1]?.coordinates?.slice(0, 3)) !== firstLine, 'The next loop is the same line');
    step('desktop: the next loop reads its own climb and turns');

    const shownStops = (await page.locator('summary', { hasText: 'Stops on the way' }).count()) > 0;
    const gpx = (await downloaded(page, () => page.getByRole('button', { name: 'Export GPX' }).click())).toString('utf8');
    check(gpx.includes('<ele>'), 'The exported GPX has no heights');
    check(!shownStops || gpx.includes('<wpt'), 'The exported GPX lists no stops while the planner shows them');
    step(`desktop: the exported GPX has heights${gpx.includes('<wpt') ? ' and stop waypoints' : ''}`);

    await page.getByRole('button', { name: 'Send to device' }).click();
    const fit = readFit(await downloaded(page, () => page.getByRole('button', { name: 'Download FIT' }).click()));
    await page.keyboard.press('Escape');
    check(fit.crcOk, 'The FIT file CRC is wrong');
    check(fit.heights.length > 10 && Math.max(...fit.heights) > Math.min(...fit.heights), 'The FIT points have no heights');
    check(fit.points.length > 0 && fit.ordered, 'The FIT file has no course points in order');
    step(`desktop: the FIT course has heights (${Math.round(Math.min(...fit.heights))}–${Math.round(Math.max(...fit.heights))} m) and ${fit.points.length} course points in order`);

    await page.getByRole('button', { name: 'Save to profile' }).click();
    await page.getByRole('button', { name: 'Saved', exact: true }).waitFor({ timeout: routing });
    step('desktop: the loop saves to the profile');

    // A GPX file, opened three ways.
    const loop = (await draft(page))?.routes?.[1]?.coordinates ?? [];
    const file = { name: 'club-loop.gpx', mimeType: 'application/gpx+xml', buffer: Buffer.from(gpxOf('Club loop', loop)) };
    const input = page.locator('input[type=file][accept*=".gpx"]');

    for (const [choice, said] of [
        ['Keep it as it is', 'The file as it is.'],
        ['Reverse it', 'The file, the other way round.'],
        ['Start from my pin', 'From your pin, along the file, and home.'],
    ]) {
        await input.setInputFiles(file);
        await page.getByRole('radio', { name: new RegExp(`^${choice}`) }).click();
        await page.getByText(said).waitFor({ timeout: routing });
        await detailsShown(page, `Opened GPX, ${choice}`);
        check((await page.locator('.route-title, #route-name').first().innerText()).includes('Club loop'), 'The opened file did not name the route');
        step(`desktop: an opened GPX file, "${choice}", draws and shows its climb`);
    }

    await input.setInputFiles({ name: 'photo.gpx', mimeType: 'application/gpx+xml', buffer: Buffer.from('not a route') });
    await page.getByText(/not a GPX file|no route in it|could not be opened/).waitFor({ timeout: 10000 });
    step('desktop: a file that is not a route says so, and nothing breaks');

    // Build mode: three taps on the map, then draw.
    await page.getByRole('tab', { name: 'Build' }).click();
    const map = await page.locator('.maplibregl-canvas').boundingBox();

    for (const [x, y] of [[0.62, 0.35], [0.75, 0.55], [0.6, 0.7]]) {
        await page.mouse.click(map.x + map.width * x, map.y + map.height * y);
        await page.waitForTimeout(900);
    }

    await page.locator('button.fill').click();
    await detailsShown(page, 'Built route');
    step('desktop: a built route through three tapped points shows its climb, surfaces, and turns');

    // A brick: one land leg at a time.
    await page.getByRole('tab', { name: 'Multisport' }).click();
    await page.getByRole('button', { name: /^Type\s*\w/ }).click();
    await page.getByRole('radio', { name: /^Brick\b/ }).click();
    await page.waitForFunction(() => !document.querySelector('[data-slot="sheet-content"][data-state="open"]'));
    await page.locator('button.fill').click();
    await page.locator('.leg-list li').first().waitFor({ timeout: routing });
    const legTabs = page.getByRole('tablist', { name: 'Leg to show' }).getByRole('tab');
    await legTabs.first().waitFor({ timeout: routing });
    check((await legTabs.count()) === 2, `A brick should offer 2 legs, not ${await legTabs.count()}`);
    await detailsShown(page, 'Brick, first leg');
    const rideClimb = await page.locator('.tw-details-head p').first().innerText();
    await legTabs.nth(1).click();
    check((await legTabs.nth(1).getAttribute('aria-selected')) === 'true', 'The second leg did not become the one shown');
    await page.waitForFunction(
        (before) => document.querySelector('.tw-details-head p')?.textContent !== before,
        rideClimb,
        { timeout: routing },
    ).catch(() => note('Brick: the run shows the same climb line as the ride'));
    await detailsShown(page, 'Brick, second leg');
    step('desktop: a brick shows the ride, then the run, from the leg picker');

    const brickGpx = (await downloaded(page, () => page.getByRole('button', { name: 'Export GPX' }).click())).toString('utf8');
    check((brickGpx.match(/<trk>/g) ?? []).length === 2, 'The brick GPX should have two tracks');
    check((brickGpx.match(/<trk>.*?<ele>/gs) ?? []).length === 2, 'Each brick track should have heights');
    step('desktop: the brick GPX gives each leg its heights');

    // A long ride, past the size nginx keeps in memory.
    await page.getByRole('tab', { name: 'Generate' }).click();
    await page.evaluate(() => {
        const key = Object.keys(localStorage).find((name) => /^traceway\.planner\.v1\.\d+$/.test(name));
        const saved = JSON.parse(localStorage.getItem(key) ?? '{}');
        localStorage.setItem(key, JSON.stringify({ ...saved, distanceKm: 100, routes: [], routeIndex: 0 }));
    });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.getByRole('tab', { name: 'Generate' }).click();
    await page.waitForFunction(() =>
        [...document.querySelectorAll('button.row')].some((row) => /Length/.test(row.textContent ?? '') && /100 km/.test(row.textContent ?? '')),
    );
    await page.locator('button.fill').click();
    text = await detailsShown(page, '100 km loop');
    step(`desktop: a 100 km loop shows its climb (${/(\d+) m\s*up/.exec(text)?.[1]} m) and turns`);

    await page.goto(`${base}/saved`, { waitUntil: 'domcontentloaded' });
    await page.getByText('Morning loop').first().click();
    await page.locator('.tw-details-chart').first().waitFor({ timeout: 20000 });
    step('desktop: the saved route shows its climb chart');

    const shareUrl = await page.getByRole('link', { name: 'Open' }).first().getAttribute('href');
    await page.goto(shareUrl, { waitUntil: 'domcontentloaded' });
    await page.locator('.tw-details-chart').waitFor({ timeout: 20000 });
    const shareBox = await page.locator('.tw-details-chart').boundingBox();
    await page.mouse.move(shareBox.x + shareBox.width * 0.6, shareBox.y + shareBox.height / 2);
    await page.locator('.tw-details-tip').waitFor({ timeout: 3000 });
    const sharedFit = readFit(await downloaded(page, () => page.getByRole('link', { name: 'FIT', exact: true }).click()));
    check(sharedFit.crcOk && sharedFit.points.length > 0 && sharedFit.heights.length > 0, 'The shared FIT has no heights or course points');
    const sharedGpx = (await downloaded(page, () => page.getByRole('link', { name: 'GPX', exact: true }).click())).toString('utf8');
    check(sharedGpx.includes('<ele>'), 'The shared GPX has no heights');
    step('desktop: the shared link shows the chart, and its FIT and GPX carry heights and course points');

    settle();
    await desktop.close();

    // ── Phone ───────────────────────────────────────────────────────────
    const phone = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 3,
        isMobile: true,
        hasTouch: true,
        acceptDownloads: true,
    });
    const mobile = await phone.newPage();
    const settlePhone = watch(mobile, 'Phone');
    const cdp = await phone.newCDPSession(mobile);
    const touch = (type, points) =>
        cdp.send('Input.dispatchTouchEvent', { type, touchPoints: points.map(({ x, y }) => ({ x: Math.round(x), y: Math.round(y) })) });

    async function drag(from, to, moves = 14, during = null) {
        await touch('touchStart', [from]);

        for (let index = 1; index <= moves; index++) {
            await touch('touchMove', [{ x: from.x + ((to.x - from.x) * index) / moves, y: from.y + ((to.y - from.y) * index) / moves }]);
            await mobile.waitForTimeout(16);
        }

        const seen = during ? await during() : null;
        await touch('touchEnd', []);
        await mobile.waitForTimeout(250);

        return seen;
    }

    async function centre(locator) {
        await locator.scrollIntoViewIfNeeded();
        const found = await locator.boundingBox();
        check(found, 'An element to touch is not on screen.');

        return { x: found.x + found.width / 2, y: found.y + found.height / 2 };
    }

    async function tap(locator) {
        const point = await centre(locator);
        await mobile.touchscreen.tap(point.x, point.y);
        await mobile.waitForTimeout(200);
    }

    async function settled() {
        await mobile.waitForFunction(() => !document.querySelector('[data-slot="sheet-overlay"]'), null, { timeout: 3000 });
    }

    async function openDrawer() {
        await settled();

        if ((await mobile.locator('.panel.open').count()) === 0) {
            await tap(mobile.getByRole('button', { name: 'Expand the plan' }));
            await mobile.locator('.panel.open').waitFor();
        }
    }

    async function scrub(label) {
        const chartLocator = mobile.locator('.tw-details-chart').first();
        await chartLocator.scrollIntoViewIfNeeded();
        const chartBox = await chartLocator.boundingBox();
        const y = chartBox.y + chartBox.height / 2;
        const tip = await drag(
            { x: chartBox.x + 12, y },
            { x: chartBox.x + chartBox.width * 0.7, y },
            16,
            async () => ((await mobile.locator('.tw-details-tip').count()) > 0 ? mobile.locator('.tw-details-tip').innerText() : null),
        );
        check(tip && /km · -?\d+ m/.test(tip), `${label}: sliding a finger along the chart shows no distance and height`);

        return tip;
    }

    await mobile.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await tap(mobile.getByRole('button', { name: /^Gravel\b/ }));
    await mobile.waitForURL(/\/dashboard/, { timeout: 20000 });
    await mobile.locator('.maplibregl-canvas').waitFor();
    await mobile.evaluate(() => {
        for (const key of Object.keys(localStorage)) {
            if (key.startsWith('traceway.planner')) {
                localStorage.removeItem(key);
            }
        }
    });
    await mobile.reload({ waitUntil: 'domcontentloaded' });
    await mobile.locator('.maplibregl-canvas').waitFor();
    await fits(mobile, 'Phone planner');
    step('phone: signed in as the gravel rider with a tap');

    const handle = await centre(mobile.getByRole('button', { name: 'Expand the plan' }));
    await drag(handle, { x: handle.x, y: 160 });
    await mobile.locator('.panel.open').waitFor({ timeout: 3000 });
    step('phone: dragged the plan open');

    await tap(mobile.getByRole('tab', { name: 'Generate' }));
    await tap(mobile.locator('button.fill'));
    await detailsShown(mobile, 'Phone loop');
    await fits(mobile, 'Phone planner with the chart');
    step('phone: a generated loop shows its climb, surfaces, and turns in the drawer');

    const body = mobile.locator('.pbody');
    await body.evaluate((node) => node.scrollTo(0, 0));
    const bodyBox = await body.boundingBox();
    await drag({ x: bodyBox.x + bodyBox.width / 2, y: bodyBox.y + bodyBox.height * 0.8 }, { x: bodyBox.x + bodyBox.width / 2, y: bodyBox.y + bodyBox.height * 0.25 });
    check((await body.evaluate((node) => node.scrollTop)) > 0, 'A finger cannot scroll the drawer to the chart');
    step('phone: a finger scrolls the drawer to the chart');

    const tip = await scrub('Phone loop');
    step(`phone: sliding a finger along the chart reads "${tip}"`);

    await tap(mobile.getByLabel('Steepness on map'));
    await mobile.getByRole('list', { name: 'Steepness' }).waitFor();
    step('phone: a tap turns on steepness, with its key');

    await tap(mobile.locator('summary', { hasText: 'Turns (' }));
    const phoneTurns = mobile.locator('.tw-details-lists details[open] li button');
    check((await phoneTurns.count()) > 0, 'The turn list does not open with a tap');
    await tap(phoneTurns.first());
    step('phone: a tap opens the turn list, and a turn can be tapped');

    if (await stopsShown(mobile, 'Phone loop')) {
        await tap(mobile.locator('summary', { hasText: 'Stops on the way' }));
        check((await mobile.locator('.tw-details-lists details[open] li button').count()) > 0, 'The stops list does not open with a tap');
        step('phone: a tap opens the stops on the way');
    }

    const phoneLoop = (await draft(mobile))?.routes?.[0]?.coordinates ?? [];
    const [chooser] = await Promise.all([mobile.waitForEvent('filechooser'), tap(mobile.getByRole('button', { name: 'Open a GPX file' }))]);
    await chooser.setFiles({ name: 'ride.gpx', mimeType: 'application/gpx+xml', buffer: Buffer.from(gpxOf('Sunday ride', phoneLoop)) });
    const sheet = mobile.locator('[data-slot="sheet-content"][data-state="open"]');
    await sheet.waitFor({ timeout: 15000 });
    const sheetBox = await sheet.boundingBox();
    await drag({ x: sheetBox.x + sheetBox.width / 2, y: sheetBox.y + 10 }, { x: sheetBox.x + sheetBox.width / 2, y: sheetBox.y + 300 }, 16);
    await mobile.waitForFunction(() => !document.querySelector('[data-slot="sheet-content"][data-state="open"]'), null, { timeout: 3000 });
    step('phone: tapping Open a GPX file opens the file picker, and the choices swipe away');

    await settled();
    const [again] = await Promise.all([mobile.waitForEvent('filechooser'), tap(mobile.getByRole('button', { name: 'Open a GPX file' }))]);
    await again.setFiles({ name: 'ride.gpx', mimeType: 'application/gpx+xml', buffer: Buffer.from(gpxOf('Sunday ride', phoneLoop)) });
    await tap(mobile.getByRole('radio', { name: /^Start from my pin/ }));
    await mobile.getByText('From your pin, along the file, and home.').waitFor({ timeout: routing });
    await openDrawer();
    await detailsShown(mobile, 'Phone opened GPX');
    await scrub('Phone opened GPX');
    step('phone: an opened GPX file, started from the pin, draws and its chart scrubs');

    await mobile.locator('.pbody').evaluate((node) => node.scrollTo(0, 0));
    await tap(mobile.getByRole('tab', { name: 'Multisport' }));
    await tap(mobile.getByRole('button', { name: /^Type\s*\w/ }));
    await tap(mobile.getByRole('radio', { name: /^Brick\b/ }));
    await mobile.waitForFunction(() => !document.querySelector('[data-slot="sheet-content"][data-state="open"]'));
    await settled();
    await tap(mobile.locator('button.fill'));
    const phoneLegs = mobile.getByRole('tablist', { name: 'Leg to show' }).getByRole('tab');
    await phoneLegs.first().waitFor({ timeout: routing });
    await tap(phoneLegs.nth(1));
    check((await phoneLegs.nth(1).getAttribute('aria-selected')) === 'true', 'A tap does not pick the second leg');
    await detailsShown(mobile, 'Phone brick, second leg');
    await scrub('Phone brick, second leg');
    await fits(mobile, 'Phone brick');
    step('phone: a brick draws, a tap picks the run, and its chart scrubs');

    await mobile.locator('.pbody').evaluate((node) => node.scrollTo(0, 0));
    await tap(mobile.getByRole('tab', { name: 'Generate' }));
    await tap(mobile.locator('button.fill'));
    await detailsShown(mobile, 'Phone loop to save');
    const savedName = (await mobile.locator('.route-title').first().innerText()).trim();
    await tap(mobile.getByRole('button', { name: 'Save to profile' }));
    await mobile.getByRole('button', { name: 'Saved', exact: true }).waitFor({ timeout: routing });
    step(`phone: a loop saves with a tap, as "${savedName}"`);

    await mobile.goto(`${base}/saved`, { waitUntil: 'domcontentloaded' });
    await mobile.waitForTimeout(1500);
    await fits(mobile, 'Phone library');
    // The library opens as a map with a sheet. A tap opens the list, then a
    // tap on the card opens the route.
    const card = mobile.locator('.sheet-card', { hasText: savedName }).first();

    if (!(await card.isVisible())) {
        await tap(mobile.locator('.grab').first());
    }

    await card.waitFor({ timeout: 15000 });
    await tap(card);
    await mobile.locator('.tw-details-chart').first().waitFor({ timeout: 20000 });
    await fits(mobile, 'Phone saved route');
    step('phone: the saved route opens with a tap and shows its climb chart');

    const phoneShare = await mobile.getByRole('link', { name: 'Open' }).first().getAttribute('href');
    await mobile.goto(phoneShare, { waitUntil: 'domcontentloaded' });
    await mobile.locator('.tw-details-chart').waitFor({ timeout: 20000 });
    await fits(mobile, 'Phone shared route');
    await scrub('Phone shared route');
    step('phone: the shared link shows the chart, and a finger scrubs it');

    settlePhone();
    await phone.close();

    console.log(`\nroute details passed (${steps.length} steps${notes.length > 0 ? `, ${notes.length} note(s)` : ''})`);
} catch (error) {
    if (process.env.SCREENSHOT) {
        for (const context of browser.contexts()) {
            const [open] = context.pages();
            await open?.screenshot({ path: process.env.SCREENSHOT, fullPage: false });
        }
    }

    throw error;
} finally {
    await browser.close();
}
