import { existsSync, mkdirSync, readdirSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from 'playwright-core';

// Every page a rider sees, at the sizes they hold it: desktop, a tablet both
// ways, a phone both ways, and a small phone. The same open page is turned
// from one size to the next, the way a phone rotates, so a layout that only
// works after a reload shows up. A page that scrolls sideways or throws an
// uncaught error fails the run. The pictures, and one contact sheet per page,
// are left for a person to look at: a test cannot tell a cramped header from
// a good one.

const base = process.env.BASE_URL || 'https://traceway.ddev.site:8443';
const out = process.env.SCREENS_DIR || join(tmpdir(), 'traceway-screens');
const local = [
    process.env.HOME,
    'Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
].join('/');
const executable = process.env.CHROMIUM_PATH || (existsSync(local) ? local : undefined);
const routing = 120_000;

const sizes = [
    ['desktop', 1440, 900],
    ['tablet-landscape', 1024, 768],
    ['tablet-portrait', 768, 1024],
    ['phone-landscape', 844, 390],
    ['phone-portrait', 390, 844],
    ['small-phone', 360, 740],
    ['tablet-landscape-again', 1024, 768],
];

mkdirSync(out, { recursive: true });

const browser = await chromium.launch({
    executablePath: executable,
    headless: true,
    args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'],
});
const failures = [];

async function signIn(page) {
    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /^Road\b/ }).click();
    await page.waitForURL(/\/dashboard/, { timeout: 20000 });
}

async function openLists(page) {
    await page.locator('.tw-details-chart').first().waitFor({ timeout: routing });

    for (const summary of await page.locator('.tw-details-lists summary').all()) {
        await summary.click();
    }
}

let shared = null;
let savedName = null;

const pages = [
    {
        name: 'home',
        url: '/',
        // The Ask your AI chat, after its animation has run.
        settle: async (page) => {
            await page.evaluate(() => document.getElementById('ask')?.scrollIntoView({ block: 'start' }));
            await page.waitForTimeout(9500);
        },
    },
    {
        name: 'planner',
        url: '/dashboard',
        auth: true,
        prepare: async (page) => {
            await page.locator('.maplibregl-canvas').waitFor();
            await page.getByRole('tab', { name: 'Generate' }).click();
            await page.locator('button.fill').click();
            await page.locator('.tw-details-chart').first().waitFor({ timeout: routing });
            // Saved here, so the library and the shared link have a route
            // on a fresh database too.
            savedName = (await page.locator('.route-title').first().innerText()).trim();
            await page.getByRole('button', { name: 'Save to profile' }).click();
            await page.getByRole('button', { name: 'Saved', exact: true }).waitFor({ timeout: routing });
        },
    },
    {
        name: 'saved',
        url: '/saved',
        auth: true,
        prepare: async (page) => {
            await page.getByText(savedName ?? 'Morning loop').first().click();
            await openLists(page);
            shared = await page.getByRole('link', { name: 'Open' }).first().getAttribute('href');
        },
    },
    {
        name: 'share',
        url: () => shared,
        prepare: openLists,
    },
    { name: 'register', url: '/register', full: true },
    { name: 'assistants', url: '/settings/assistants', auth: true, full: true },
    { name: 'privacy', url: '/settings/privacy', auth: true, full: true },
];

for (const spec of pages) {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    page.on('pageerror', (error) => failures.push(`${spec.name}: page error: ${error.message}`));

    if (spec.auth) {
        await signIn(page);
    }

    const url = typeof spec.url === 'function' ? spec.url() : `${base}${spec.url}`;

    if (url === null) {
        failures.push(`${spec.name}: no address to open`);
        await context.close();

        continue;
    }

    await page.goto(url, { waitUntil: 'networkidle' });
    await spec.prepare?.(page);

    for (const [label, width, height] of sizes) {
        await page.setViewportSize({ width, height });
        await page.waitForTimeout(800);
        await spec.settle?.(page);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

        if (overflow > 1) {
            failures.push(`${spec.name} at ${label} (${width}×${height}) scrolls sideways by ${overflow}px`);
        }

        await page.screenshot({ path: join(out, `${spec.name}--${String(sizes.findIndex(([l]) => l === label)).padStart(2, '0')}-${label}.png`), fullPage: !!spec.full });
    }

    await context.close();
}

// One sheet per page, its sizes side by side.
const sheet = await browser.newPage({ viewport: { width: 2000, height: 1000 } });

for (const spec of pages) {
    const files = readdirSync(out).filter((file) => file.startsWith(`${spec.name}--`)).sort();
    const figures = files
        .map((file) => `<figure><img src="data:image/png;base64,${readFileSync(join(out, file)).toString('base64')}"><figcaption>${file.replace(/^.*?--\d+-/, '').replace('.png', '')}</figcaption></figure>`)
        .join('');
    await sheet.setContent(`<style>body{margin:0;padding:12px;background:#ddd;font:14px system-ui;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start}figure{margin:0;background:#fff;padding:6px}img{display:block;height:${spec.full ? 900 : 360}px;width:auto}figcaption{padding-top:4px}</style>${figures}`);
    await sheet.screenshot({ path: join(out, `sheet-${spec.name}.png`), fullPage: true });
}

await browser.close();

console.log(`screens in ${out}: one sheet-<page>.png per page`);

if (failures.length > 0) {
    console.error(failures.join('\n'));
    process.exit(1);
}

console.log('screens passed');
