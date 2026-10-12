import { chromium } from 'playwright-core';

const base = process.env.BASE_URL || 'https://traceway.ddev.site:8443';
const executable = process.env.CHROMIUM_PATH || [
    process.env.HOME,
    'Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
].join('/');

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
