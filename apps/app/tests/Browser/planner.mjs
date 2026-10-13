import { existsSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = process.env.BASE_URL || 'https://traceway.ddev.site:8443';
// A local Chrome for Testing when there is one, else the Chromium that
// `playwright-core install chromium` puts in Playwright's cache, as CI does.
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

    // The map takes the keyboard: arrows move it, Enter places the start.
    const draft = () => desktop.evaluate(() => {
        const key = Object.keys(localStorage).find((name) => /^traceway\.planner\.v1\.\d+$/.test(name));

        return key ? localStorage.getItem(key) : null;
    });
    await desktop.locator('.maplibregl-canvas').waitFor();
    await desktop.locator('.maplibregl-canvas').focus();
    await desktop.keyboard.press('ArrowRight');
    await desktop.waitForTimeout(400);
    const beforeEnter = await draft();
    await desktop.keyboard.press('Enter');
    await desktop.waitForTimeout(300);

    if ((await draft()) === beforeEnter) {
        throw new Error('Enter on the map did not place the start.');
    }

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
    await open(phone, '/login');
    const phoneRoad = phone.getByRole('button', { name: /^Road\b/ });
    await phoneRoad.waitFor();
    const box = await phoneRoad.boundingBox();
    if (!box || box.width < 40) {
        throw new Error('Road is not tappable on a phone');
    }
    await fits(phone, 'Login on a phone');

    console.log('browser smoke passed');
} finally {
    await browser.close();
}
