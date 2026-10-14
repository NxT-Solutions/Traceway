import { existsSync } from 'node:fs';
import { chromium } from 'playwright-core';

// Drives the planner on a phone with real touch input. Chrome turns the
// touches into touch pointer events, as it does on a device, so the drawer,
// the leg grips, the sheets, the map, the player, and the finish pins see
// the same gestures a rider makes.

const base = process.env.BASE_URL || 'https://traceway.ddev.site:8443';
// A local Chrome for Testing when there is one, else the Chromium that
// `playwright-core install chromium` puts in Playwright's cache, as CI does.
const local = [
    process.env.HOME,
    'Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
].join('/');
const executable = process.env.CHROMIUM_PATH || (existsSync(local) ? local : undefined);
const routing = 90_000;

const browser = await chromium.launch({ executablePath: executable, headless: true });
const steps = [];

function step(name) {
    steps.push(name);
    console.log(`· ${name}`);
}

async function fits(page, label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    if (overflow > 1) {
        throw new Error(`${label} scrolls sideways by ${overflow}px`);
    }
}

try {
    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 3,
        isMobile: true,
        hasTouch: true,
    });
    const page = await context.newPage();
    // An uncaught error on the page fails the run, even when it still looks right.
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    const cdp = await context.newCDPSession(page);

    async function touch(type, points) {
        await cdp.send('Input.dispatchTouchEvent', {
            type,
            touchPoints: points.map(({ x, y }) => ({ x: Math.round(x), y: Math.round(y) })),
        });
    }

    async function drag(from, to, moves = 14, hold = 0) {
        await touch('touchStart', [from]);

        if (hold > 0) {
            await page.waitForTimeout(hold);
        }

        for (let index = 1; index <= moves; index++) {
            await touch('touchMove', [{
                x: from.x + ((to.x - from.x) * index) / moves,
                y: from.y + ((to.y - from.y) * index) / moves,
            }]);
            await page.waitForTimeout(16);
        }

        await touch('touchEnd', []);
        await page.waitForTimeout(250);
    }

    async function centre(locator) {
        await locator.scrollIntoViewIfNeeded();
        const box = await locator.boundingBox();

        if (!box) {
            throw new Error('An element to touch is not on screen.');
        }

        return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
    }

    // A closing sheet keeps its overlay until the fade ends.
    async function settled() {
        await page.waitForFunction(() => !document.querySelector('[data-slot="sheet-overlay"]'), null, { timeout: 3000 });
    }

    async function tap(locator) {
        const point = await centre(locator);
        await page.touchscreen.tap(point.x, point.y);
        await page.waitForTimeout(200);
    }

    async function draft() {
        return page.evaluate(() => {
            const key = Object.keys(localStorage).find((name) => /^traceway\.planner\.v1\.\d+$/.test(name));

            return key ? JSON.parse(localStorage.getItem(key) ?? 'null') : null;
        });
    }

    // After the map frames the route, every pin sits between the player
    // card and the drawer.
    async function framed(selector, label) {
        await page.waitForTimeout(800);
        const card = await page.locator('.player-card').boundingBox();
        const panel = await page.locator('.panel').boundingBox();
        const pins = await page.locator(selector).evaluateAll((nodes) =>
            nodes.map((node) => {
                const box = node.getBoundingClientRect();

                return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
            }),
        );
        const top = card ? card.y + card.height : 0;
        const bottom = panel ? panel.y : 844;

        if (pins.length === 0 || pins.some((pin) => pin.y < top || pin.y > bottom || pin.x < 0 || pin.x > 390)) {
            throw new Error(`${label}: the route is not framed in the open map (${JSON.stringify(pins)} between ${top} and ${bottom})`);
        }
    }

    async function legNames() {
        return page.locator('section[aria-label="Legs"] button.field').evaluateAll((fields) =>
            fields.map((field) => field.querySelector('span')?.textContent ?? ''),
        );
    }

    async function drawn() {
        await page.locator('.leg-list li').first().waitFor({ timeout: routing });
        await page.locator('button.fill:not([disabled])').waitFor({ timeout: routing });
    }

    async function openDrawer() {
        await settled();

        if (await page.locator('.panel.open').count() === 0) {
            await tap(page.getByRole('button', { name: 'Expand the plan' }));
            await page.locator('.panel.open').waitFor();
        }
    }

    async function closeDrawer() {
        await settled();

        if (await page.locator('.panel.open').count() > 0) {
            await tap(page.getByRole('button', { name: 'Collapse the plan' }));
            await page.waitForFunction(() => !document.querySelector('.panel.open'));
        }
    }

    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await tap(page.getByRole('button', { name: /^Road\b/ }));
    await page.waitForURL(/\/dashboard/, { timeout: 20000 });
    await page.locator('.maplibregl-canvas').waitFor();
    await fits(page, 'Planner');
    step('signed in with a tap');

    const handle = page.getByRole('button', { name: 'Expand the plan' });
    const handleAt = await centre(handle);
    await drag(handleAt, { x: handleAt.x, y: 160 });
    await page.locator('.panel.open').waitFor({ timeout: 3000 });
    step('dragged the drawer open');

    const collapse = page.getByRole('button', { name: 'Collapse the plan' });
    const collapseAt = await centre(collapse);
    await drag(collapseAt, { x: collapseAt.x, y: 820 });
    await page.waitForFunction(() => !document.querySelector('.panel.open'), null, { timeout: 3000 });
    step('dragged the drawer shut');

    await openDrawer();
    await tap(page.getByRole('tab', { name: 'Multisport' }));
    await page.getByRole('button', { name: /^Type\s*\w/ }).waitFor();
    await fits(page, 'Multisport planner');
    step('opened Multisport');

    await tap(page.getByRole('button', { name: /^Type\s*\w/ }));
    await tap(page.getByRole('radio', { name: /^Brick\b/ }));
    await page.waitForFunction(() => !document.querySelector('[data-slot="sheet-content"][data-state="open"]'));
    step('chose a brick from the session sheet');

    await tap(page.getByRole('radio', { name: 'Loops from T' }));
    let names = await legNames();

    if (names.join() !== 'Road,Run') {
        throw new Error(`A brick should be Road then Run, not ${names.join(', ')}`);
    }

    const grips = page.locator('section[aria-label="Legs"] .grip');
    const runGrip = await centre(grips.nth(1));
    const roadGrip = await centre(grips.nth(0));
    await drag(runGrip, { x: runGrip.x, y: roadGrip.y - 6 }, 16, 60);
    names = await legNames();

    if (names.join() !== 'Run,Road') {
        throw new Error(`Dragging the run grip up should give Run, Road, not ${names.join(', ')}`);
    }

    step('dragged a leg above another with its grip');

    await tap(page.locator('section[aria-label="Legs"] button.field').nth(0));
    await tap(page.getByRole('button', { name: 'Move this leg later' }));
    names = await legNames();

    if (names.join() !== 'Road,Run') {
        throw new Error(`Move later should give Road, Run, not ${names.join(', ')}`);
    }

    step('moved a leg with a single tap');

    await tap(page.getByRole('button', { name: /^Colours\b/ }));
    const sheet = page.locator('[data-slot="sheet-content"][data-state="open"]');
    await sheet.waitFor();
    const sheetBox = await sheet.boundingBox();

    if (!sheetBox) {
        throw new Error('The colour sheet did not open.');
    }

    await drag(
        { x: sheetBox.x + sheetBox.width / 2, y: sheetBox.y + 10 },
        { x: sheetBox.x + sheetBox.width / 2, y: sheetBox.y + 260 },
        16,
    );
    await page.waitForFunction(() => !document.querySelector('[data-slot="sheet-content"][data-state="open"]'), null, { timeout: 3000 });
    await settled();
    step('swiped a sheet down to close it');

    await closeDrawer();
    const before = await draft();
    const mapBox = await page.locator('.maplibregl-canvas').boundingBox();

    if (!mapBox) {
        throw new Error('The map is not on screen.');
    }

    await page.touchscreen.tap(mapBox.x + mapBox.width / 2 + 30, mapBox.y + mapBox.height * 0.35);
    await page.waitForTimeout(400);
    const after = await draft();

    if (!before || !after || (before.latitude === after.latitude && before.longitude === after.longitude)) {
        throw new Error('Tapping the map did not move the transition.');
    }

    step('moved the transition with a tap on the map');

    await openDrawer();
    await tap(page.locator('button.fill'));
    await drawn();
    const legs = await page.locator('.leg-list li').count();

    if (legs !== 2) {
        throw new Error(`The brick should draw 2 legs, not ${legs}`);
    }

    step('drew the brick through the routing engine');

    await closeDrawer();
    const player = page.locator('.player-card');
    await player.waitFor();
    await framed('.tw-start-pin', 'The brick');
    const track = await page.locator('.player-card input[type="range"]').boundingBox();

    if (!track) {
        throw new Error('The player slider is not on screen.');
    }

    await drag(
        { x: track.x + 8, y: track.y + track.height / 2 },
        { x: track.x + track.width * 0.6, y: track.y + track.height / 2 },
    );
    const at = Number(await page.locator('.player-card .where b').textContent());

    if (!(at > 0)) {
        throw new Error('Sliding the player did not move along the route.');
    }

    if (await page.locator('.player-pin').count() !== 1) {
        throw new Error('The player pin is not on the map.');
    }

    step(`slid the player to ${at} km with a finger`);

    await tap(page.getByRole('button', { name: 'Play the route' }));
    await page.waitForTimeout(1500);
    const later = Number(await page.locator('.player-card .where b').textContent());
    await tap(page.getByRole('button', { name: 'Pause' }));

    if (!(later > at)) {
        throw new Error('Play did not run the route forward.');
    }

    step('played and paused the route');

    await openDrawer();
    await tap(page.getByRole('radio', { name: 'Chain' }));
    await closeDrawer();
    const chainMap = await page.locator('.maplibregl-canvas').boundingBox();

    if (!chainMap) {
        throw new Error('The map is not on screen.');
    }

    await page.touchscreen.tap(chainMap.x + chainMap.width * 0.3, chainMap.y + chainMap.height * 0.3);
    await page.waitForTimeout(300);
    await page.touchscreen.tap(chainMap.x + chainMap.width * 0.7, chainMap.y + chainMap.height * 0.45);
    await openDrawer();
    await drawn();
    step('set both chain finishes with taps and drew the chain');

    await closeDrawer();
    const pin = page.locator('.finish-pin').last();
    await pin.waitFor();
    const pinAt = await centre(pin);
    const finishBefore = (await draft())?.multisport?.legs?.[1]?.finish;
    await drag(pinAt, { x: pinAt.x - 60, y: pinAt.y + 40 }, 16);
    await page.waitForTimeout(400);
    const finishAfter = (await draft())?.multisport?.legs?.[1]?.finish;

    if (!finishBefore || !finishAfter || finishBefore.join() === finishAfter.join()) {
        throw new Error('Dragging the finish pin did not move the finish.');
    }

    await openDrawer();
    await drawn();
    await closeDrawer();
    await framed('.tw-start-pin, .finish-pin', 'The chain');
    step('dragged a finish pin and the chain drew again, framed in the open map');

    await fits(page, 'Planner after every gesture');

    if (pageErrors.length > 0) {
        throw new Error(`Uncaught page errors:\n${pageErrors.join('\n')}`);
    }

    console.log(`mobile gestures passed (${steps.length} steps)`);
} catch (error) {
    if (process.env.SCREENSHOT) {
        const [page] = browser.contexts()[0]?.pages() ?? [];
        await page?.screenshot({ path: process.env.SCREENSHOT });
    }

    throw error;
} finally {
    await browser.close();
}
