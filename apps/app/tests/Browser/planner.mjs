import { existsSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = process.env.BASE_URL || 'https://traceway.ddev.site:8443';
// A local Chrome for Testing when there is one, else the Chromium that
// `playwright-core install chromium` puts in Playwright's cache. CI installs
// only its headless shell, which is what a headless launch runs.
const local = [
    process.env.HOME,
    'Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
].join('/');
const executable = process.env.CHROMIUM_PATH || (existsSync(local) ? local : undefined);

const browser = await chromium.launch({
    executablePath: executable,
    headless: true,
});

async function open(page, path) {
    const response = await page.goto(`${base}${path}`, { waitUntil: 'domcontentloaded', timeout: 20000 });

    if (!response) {
        throw new Error(`${path} did not answer`);
    }

    return response;
}

// A card asks the routing engine for the nearest road. When the public
// router is busy, the card says so and offers Try again, and the walk
// presses it as a rider would, up to twice. A spot with no road near it
// fails at once.
async function answered(page, action, label) {
    const card = page.locator('.tap-card');
    const missed = card.locator('.tap-note', { hasText: /busy|close enough/ });

    for (let asked = 0; ; asked++) {
        try {
            await action.or(missed).first().waitFor({ timeout: 30000 });
        } catch (error) {
            const said = await card.allInnerTexts();
            const status = await page.locator('.map-search-note, .note[role="status"]').allInnerTexts();
            throw new Error(`${label}. Card: ${JSON.stringify(said)}. Status: ${JSON.stringify(status)}. ${error.message}`);
        }

        if (await action.isVisible()) {
            return action;
        }

        const note = await missed.innerText();

        if (!note.includes('busy') || asked === 2) {
            throw new Error(`${label}. Card: ${note}`);
        }

        await card.getByRole('button', { name: 'Try again' }).click();
        await missed.waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {});
    }
}

async function fits(page, label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    if (overflow > 1) {
        throw new Error(`${label} scrolls sideways by ${overflow}px at ${page.viewportSize().width}px wide`);
    }
}

try {
    const desktop = await browser.newPage({
        ignoreHTTPSErrors: true,
        viewport: { width: 1280, height: 800 },
    });
    // An uncaught error on a page fails the run, even when the page still looks right.
    const pageErrors = [];
    desktop.on('pageerror', (error) => pageErrors.push(`desktop: ${error.message}`));

    await open(desktop, '/dashboard');
    if (!desktop.url().includes('/login')) {
        throw new Error(`A guest reached ${desktop.url()}`);
    }

    const road = desktop.getByRole('button', { name: /^Road\b/ });
    await road.waitFor();
    await fits(desktop, 'Login');
    await road.click();
    await desktop.waitForURL(/\/dashboard/, { timeout: 20000 });
    await fits(desktop, 'Planner');

    // The map takes the keyboard: arrows move it, Enter asks what to do at
    // the cross, and Enter again places the start there.
    const draft = () => desktop.evaluate(() => {
        const key = Object.keys(localStorage).find((name) => /^traceway\.planner\.v1\.\d+$/.test(name));

        return key ? JSON.parse(localStorage.getItem(key) ?? 'null') : null;
    });
    const startOf = async () => {
        const saved = await draft();

        return saved ? `${saved.latitude.toFixed(5)},${saved.longitude.toFixed(5)}` : null;
    };
    const canvas = desktop.locator('.maplibregl-canvas');
    await canvas.waitFor();
    await canvas.focus();
    await desktop.keyboard.press('ArrowRight');
    await desktop.waitForTimeout(400);
    const beforeEnter = await startOf();
    await desktop.keyboard.press('Enter');
    await desktop.locator('.tap-card').waitFor({ timeout: 3000 });

    if ((await startOf()) !== beforeEnter) {
        throw new Error('Enter on the map moved the start before the rider chose to.');
    }

    await answered(desktop, desktop.locator('.tap-card .fill:focus', { hasText: 'Start here' }), 'No focused "Start here" after Enter on the map');
    await desktop.keyboard.press('Enter');
    await desktop.waitForTimeout(300);

    if ((await startOf()) === beforeEnter) {
        throw new Error('Enter on "Start here" did not place the start.');
    }

    // Undo puts the start back where it was.
    const placed = await startOf();
    await canvas.focus();
    await desktop.keyboard.press('ArrowRight');
    await desktop.waitForTimeout(400);
    await desktop.keyboard.press('Enter');
    // The card finds the nearest road first, then offers its actions.
    await answered(desktop, desktop.locator('.tap-card .fill:focus', { hasText: 'Start here' }), 'No focused "Start here" after a second Enter');
    await desktop.keyboard.press('Enter');
    await desktop.waitForTimeout(300);

    if ((await startOf()) === placed) {
        throw new Error('A second "Start here" did not move the start.');
    }

    await desktop.getByRole('button', { name: 'Undo' }).click();
    await desktop.waitForTimeout(300);

    if ((await startOf()) !== placed) {
        throw new Error(`Undo left the start at ${await startOf()}, not ${placed}.`);
    }

    // Panning the map, or a tap that is not followed by a choice, never moves the start.
    const mapBox = await canvas.boundingBox();
    const middle = { x: mapBox.x + mapBox.width * 0.6, y: mapBox.y + mapBox.height * 0.45 };
    await desktop.mouse.move(middle.x, middle.y);
    await desktop.mouse.down();
    await desktop.mouse.move(middle.x + 120, middle.y + 60, { steps: 8 });
    await desktop.mouse.up();
    await desktop.mouse.click(middle.x, middle.y);
    await desktop.locator('.tap-card').waitFor({ timeout: 3000 });
    await desktop.keyboard.press('Escape');
    await desktop.locator('.tap-card').waitFor({ state: 'detached', timeout: 3000 });

    if ((await startOf()) !== placed) {
        throw new Error('Panning or a stray tap moved the start.');
    }

    // The start pin drags.
    const pin = await desktop.locator('.tw-start-pin').boundingBox();
    await desktop.mouse.move(pin.x + pin.width / 2, pin.y + pin.height / 2);
    await desktop.mouse.down();
    await desktop.mouse.move(pin.x + pin.width / 2 + 80, pin.y + pin.height / 2 + 40, { steps: 8 });
    await desktop.mouse.up();

    // The dropped pin finds its road first, which takes a moment on the
    // public router.
    for (let waited = 0; waited < 15000 && (await startOf()) === placed; waited += 250) {
        await desktop.waitForTimeout(250);
    }

    if ((await startOf()) === placed) {
        const status = await desktop.locator('.note[role="status"]').allInnerTexts();
        throw new Error(`Dragging the start pin did not move the start. Status: ${JSON.stringify(status)}`);
    }

    // The search bar on the map forgives a typo with names from the map
    // itself, before it asks anyone: the planner opens on Ghent.
    const search = desktop.getByRole('combobox');
    await search.click();
    await search.fill('gentbruge');
    await desktop.getByRole('option', { name: /^Gentbrugge\b/ }).first().waitFor({ timeout: 5000 });
    await search.fill('');

    // A place found by name: the Start row puts the cursor in the bar, the
    // map goes to the pick, and the card asks before the start moves.
    await desktop.getByRole('button', { name: /^Start\b/ }).click();
    await desktop.getByRole('combobox', { name: 'Search for your start' }).fill('Westerlo');
    await desktop.keyboard.press('Enter');
    const westerlo = desktop.getByRole('option', { name: /^Westerlo\b/ }).first();
    await westerlo.waitFor({ timeout: 15000 });
    await westerlo.click();
    await (await answered(desktop, desktop.getByRole('button', { name: 'Start here' }), 'No "Start here" for Westerlo')).click();
    await desktop.getByRole('button', { name: /^Start\s*Westerlo/ }).waitFor({ timeout: 5000 });

    if (!(await startOf())?.startsWith('51.0')) {
        throw new Error(`Picking Westerlo put the start at ${await startOf()}.`);
    }

    // In Build, a point found by name sits on a road and joins the list.
    await desktop.getByRole('tab', { name: 'Build' }).click();
    await desktop.getByRole('button', { name: 'Add a point' }).click();
    await desktop.getByRole('combobox', { name: 'Search for a point to add' }).fill('Oevel');
    await desktop.keyboard.press('Enter');
    const oevel = desktop.getByRole('option', { name: /^Oevel\b/ }).first();
    await oevel.waitFor({ timeout: 15000 });
    await oevel.click();
    await (await answered(desktop, desktop.getByRole('button', { name: 'Add a point here' }), 'No "Add a point here" for Oevel')).click();
    await desktop.locator('.pts .pt[data-point]').first().waitFor({ timeout: 15000 });
    await desktop.getByRole('button', { name: 'Undo' }).click();
    await desktop.locator('.pts .pt[data-point]').first().waitFor({ state: 'detached', timeout: 3000 });
    await desktop.getByRole('tab', { name: 'Generate' }).click();

    // A GPX file opens in the planner with three ways to ride it.
    await desktop.locator('input[type=file][accept*=".gpx"]').setInputFiles({
        name: 'club-loop.gpx',
        mimeType: 'application/gpx+xml',
        buffer: Buffer.from(
            '<gpx><trk><name>Club loop</name><trkseg><trkpt lat="51.05" lon="3.72"/><trkpt lat="51.06" lon="3.73"/><trkpt lat="51.05" lon="3.74"/></trkseg></trk></gpx>',
        ),
    });
    await desktop.getByRole('radio', { name: /^Start from my pin\b/ }).waitFor({ timeout: 10000 });
    await desktop.getByRole('radio', { name: /^Reverse it\b/ }).waitFor();
    await desktop.keyboard.press('Escape');

    await desktop.getByRole('tab', { name: 'Multisport' }).click();
    await desktop.getByRole('button', { name: /^Type\s*\w/ }).click();
    await desktop.getByRole('radio', { name: /^Triathlon\b/ }).click();
    await desktop
        .getByRole('button', { name: /^Type\s*Triathlon/ })
        .waitFor({ timeout: 5000 });
    await desktop.getByRole('button', { name: /^Swim\b/ }).waitFor();
    await desktop.getByRole('radio', { name: 'Chain' }).click();
    await desktop.getByText('Tap where leg 2 ends').waitFor();
    await fits(desktop, 'Multisport planner');

    // The transition is found by name too.
    await desktop.getByRole('button', { name: /Change the transition/ }).click();
    await desktop.getByRole('combobox', { name: 'Search for the transition' }).fill('Oevel');
    await desktop.keyboard.press('Enter');
    const transition = desktop.getByRole('option', { name: /^Oevel\b/ }).first();
    await transition.waitFor({ timeout: 15000 });
    await transition.click();
    await (await answered(desktop, desktop.getByRole('button', { name: 'Move the transition here' }), 'No "Move the transition here" for Oevel')).click();
    await desktop.locator('section[aria-label="Legs"]').getByText('Oevel').waitFor({ timeout: 5000 });

    await open(desktop, '/saved');
    await desktop.getByRole('link', { name: 'Plan your route' }).waitFor();

    await open(desktop, '/settings/profile');
    await desktop.getByLabel('Name').waitFor();
    await desktop.getByLabel('Email address').waitFor();

    await open(desktop, '/settings/security');
    await desktop.getByRole('button', { name: 'Confirm password' }).waitFor();

    const phone = await browser.newPage({
        ignoreHTTPSErrors: true,
        viewport: { width: 390, height: 844 },
    });
    phone.on('pageerror', (error) => pageErrors.push(`phone: ${error.message}`));
    await open(phone, '/login');
    const phoneRoad = phone.getByRole('button', { name: /^Road\b/ });
    await phoneRoad.waitFor();
    const box = await phoneRoad.boundingBox();
    if (!box || box.width < 40) {
        throw new Error('Road is not tappable on a phone');
    }
    await fits(phone, 'Login on a phone');

    if (pageErrors.length > 0) {
        throw new Error(`Uncaught page errors:\n${pageErrors.join('\n')}`);
    }

    console.log('browser smoke passed');
} finally {
    await browser.close();
}
