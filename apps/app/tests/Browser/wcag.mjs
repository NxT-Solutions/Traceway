import AxeBuilder from '@axe-core/playwright';
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

// DDEV serves the content site and the planner from one host. CI runs
// them apart, so the content pages can come from their own address.
const content = process.env.CONTENT_URL || base;
const contentPages = ['/', '/support'];
const authPages = ['/login', '/register', '/forgot-password'];
const signedInPages = ['/dashboard', '/saved', '/settings/profile', '/settings/privacy', '/settings/assistants', '/settings/appearance'];

const browser = await chromium.launch({ executablePath: executable, headless: true });
const failures = [];

async function scan(page, path, origin = base) {
    const response = await page.goto(`${origin}${path}`, { waitUntil: 'domcontentloaded', timeout: 20000 });

    if (!response || response.status() >= 400) {
        failures.push(`${path} returned ${response ? response.status() : 'no response'}`);
        return;
    }

    await audit(page, path);
}

/**
 * Checks the page as it stands, so a state reached by clicking, such as an
 * open sheet, is checked too.
 */
async function audit(page, label) {
    // axe-core 4.13 turns oklch() into the wrong sRGB color, so its
    // contrast numbers are not the ratios in WcagTest.php.
    const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .disableRules(['color-contrast'])
        .exclude('.maplibregl-canvas')
        .exclude('.maplibregl-map')
        .analyze();

    for (const violation of result.violations) {
        const nodes = violation.nodes.map((node) => node.target.join(' ')).slice(0, 3).join('; ');
        failures.push(`${label} [${page.viewportSize().width}px] ${violation.id}: ${violation.help} (${nodes})`);
    }
}

try {
    const desktopContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 800 } });
    const phoneContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 390, height: 844 } });
    const desktop = await desktopContext.newPage();
    const phone = await phoneContext.newPage();

    for (const path of contentPages) {
        await scan(desktop, path, content);
        await scan(phone, path, content);
    }

    for (const path of authPages) {
        await scan(desktop, path);
        await scan(phone, path);
    }

    await desktop.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await desktop.getByRole('button', { name: /^Road\b/ }).click();
    await desktop.waitForURL(/\/dashboard/, { timeout: 20000 });

    for (const path of signedInPages) {
        await scan(desktop, path);
    }

    await scan(desktop, '/settings/security');

    // The multisport plan, and the sheets it opens.
    await scan(desktop, '/dashboard');
    await desktop.getByRole('tab', { name: 'Multisport' }).click();
    await desktop.getByRole('button', { name: /^Type\s*\w/ }).waitFor();
    await audit(desktop, '/dashboard multisport');
    await desktop.getByRole('button', { name: /^Type\s*\w/ }).click();
    await desktop.getByRole('radio', { name: /^Triathlon\b/ }).waitFor();
    await audit(desktop, '/dashboard session sheet');
    await desktop.getByRole('radio', { name: /^Triathlon\b/ }).click();
    await desktop.getByRole('button', { name: /^Colours\b/ }).click();
    await desktop.getByRole('radiogroup', { name: 'Colour set' }).waitFor();
    await audit(desktop, '/dashboard colour sheet');

    await phone.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await phone.getByRole('button', { name: /^Road\b/ }).click();
    await phone.waitForURL(/\/dashboard/, { timeout: 20000 });
    await phone.getByRole('button', { name: 'Expand the plan' }).click();
    await phone.getByRole('tab', { name: 'Multisport' }).click();
    await phone.getByRole('button', { name: /^Type\s*\w/ }).waitFor();
    await audit(phone, '/dashboard multisport');

    if (failures.length > 0) {
        console.error(failures.join('\n'));
        process.exit(1);
    }

    console.log('wcag axe passed');
} finally {
    await browser.close();
}
