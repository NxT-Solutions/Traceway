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
const contentPages = ['/', '/support', '/privacy', '/terms', '/cookies', '/legal'];
const authPages = ['/login', '/register', '/forgot-password'];
// Public planner pages, open to anyone.
const publicPages = ['/status'];
const signedInPages = ['/dashboard', '/saved', '/settings/profile', '/settings/privacy', '/settings/assistants', '/settings/devices', '/settings/appearance', '/settings/whats-new', '/admin', '/admin/traffic', '/admin/users', '/admin/users/create', '/admin/users/2'];

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
    desktop.on('pageerror', (error) => failures.push(`desktop page error: ${error.message}`));
    phone.on('pageerror', (error) => failures.push(`phone page error: ${error.message}`));

    for (const path of contentPages) {
        await scan(desktop, path, content);
        await scan(phone, path, content);
    }

    for (const path of authPages) {
        await scan(desktop, path);
        await scan(phone, path);
    }

    for (const path of publicPages) {
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

    // The bell in the header: open, checked, closed with Escape, and one release read.
    await scan(desktop, '/dashboard');
    const bell = desktop.getByRole('button', { name: /^What's new/ });
    const unreadBefore = Number((await bell.getAttribute('aria-label')).match(/(\d+) unread/)?.[1] ?? 0);
    await bell.click();
    await desktop.locator('.rb-panel').waitFor();
    await audit(desktop, "/dashboard what's new");
    await desktop.keyboard.press('Escape');
    if ((await desktop.locator('.rb-panel').count()) !== 0 || !(await bell.evaluate((node) => node === document.activeElement))) {
        failures.push("/dashboard what's new: Escape did not close the panel and give focus back to the bell");
    }
    if (unreadBefore > 0) {
        await bell.click();
        await desktop.getByRole('button', { name: /^Mark v.+ as read$/ }).first().click();
        await desktop.waitForFunction(
            (before) => !document.querySelector('.rb-button')?.getAttribute('aria-label')?.includes(`${before} unread`),
            unreadBefore,
        );
        await desktop.keyboard.press('Escape');
    }

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

    // Finding a place by name, and the card a tap on the map opens.
    await desktop.goto(`${base}/dashboard`, { waitUntil: 'domcontentloaded' });
    await desktop.getByRole('tab', { name: 'Generate' }).click();
    await desktop.getByRole('button', { name: /^Start\b/ }).click();
    await desktop.getByRole('combobox', { name: 'Search for your start' }).fill('Westerlo');
    await desktop.keyboard.press('Enter');
    await desktop.getByRole('option', { name: /^Westerlo\b/ }).first().waitFor({ timeout: 15000 });
    await audit(desktop, '/dashboard place search');
    await desktop.keyboard.press('Escape');
    await desktop.waitForFunction(() => !document.querySelector('[data-slot="sheet-content"][data-state="open"]'));
    await desktop.locator('.maplibregl-canvas').focus();
    await desktop.keyboard.press('Enter');
    await desktop.locator('.tap-card').waitFor({ timeout: 3000 });
    await audit(desktop, '/dashboard tap card');
    await desktop.keyboard.press('Escape');

    // A route with what it shows about itself: the climb, the steepness on
    // the map, and the stops and turns, opened.
    async function openDetails(page) {
        await page.locator('.tw-details-chart').first().waitFor({ timeout: 120000 });

        for (const summary of await page.locator('.tw-details-lists summary').all()) {
            await summary.click();
        }

        const steepness = page.getByRole('checkbox', { name: /steepness/i }).first();

        if ((await steepness.count()) > 0) {
            await steepness.check();
        }
    }

    await desktop.goto(`${base}/dashboard`, { waitUntil: 'domcontentloaded' });
    await desktop.getByRole('tab', { name: 'Generate' }).click();
    await desktop.locator('button.fill').click();
    await openDetails(desktop);
    await audit(desktop, '/dashboard route details');

    // A shared link, with how the ride was planned. Its address is signed,
    // so the route is saved here and opened from the library, the same on
    // a fresh database as on a used one.
    const savedName = (await desktop.locator('.route-title').first().innerText()).trim();
    await desktop.getByRole('button', { name: 'Save to profile' }).click();
    await desktop.getByRole('button', { name: 'Saved', exact: true }).waitFor({ timeout: 120000 });
    await desktop.goto(`${base}/saved`, { waitUntil: 'networkidle' });
    await desktop.getByText(savedName).first().click();
    await openDetails(desktop);
    await audit(desktop, '/saved route open');
    const shared = await desktop.getByRole('link', { name: 'Open' }).first().getAttribute('href', { timeout: 5000 }).catch(() => null);

    if (shared === null) {
        failures.push('/share: the road rider has no saved route to open');
    } else {
        for (const page of [desktop, phone]) {
            await page.goto(shared, { waitUntil: 'domcontentloaded' });
            await openDetails(page);
            await audit(page, '/share');
        }
    }

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
