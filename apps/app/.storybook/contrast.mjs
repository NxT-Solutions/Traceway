// Checks that every docs page of the built Storybook can be read, in Paper and
// in Ink, through axe's colour-contrast rule.
//
// The Vitest run (`bun run test:storybook`) checks every story with all of
// axe's rules, colour contrast among them, in Paper and in Ink. It does not
// open the docs pages, where MDX text and the token tables sit on Storybook's
// own ground. This covers those, and only those: checking each story again
// took CI a quarter of an hour.
//
// Run after `bun run build-storybook`: `node .storybook/contrast.mjs`. It
// writes storybook-static/contrast.json and fails when anything is unreadable.

import { createReadStream, existsSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import AxeBuilder from '@axe-core/playwright';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../storybook-static/', import.meta.url));
const index = join(root, 'index.json');

if (!existsSync(index)) {
    console.error('contrast: storybook-static/index.json is missing. Run `bun run build-storybook` first.');
    process.exit(1);
}

const types = {
    '.html': 'text/html',
    '.js': 'text/javascript',
    '.mjs': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.woff2': 'font/woff2',
};

// A plain static server, so the check needs nothing but the build.
const server = createServer((request, response) => {
    const path = normalize(decodeURIComponent(new URL(request.url ?? '/', 'http://x').pathname)).replace(/^(\.\.[/\\])+/, '');
    let file = join(root, path);

    if (existsSync(file) && statSync(file).isDirectory()) {
        file = join(file, 'index.html');
    }

    if (!file.startsWith(root) || !existsSync(file)) {
        response.writeHead(404).end();

        return;
    }

    // A read that fails ends the answer, so the page sees a broken file
    // rather than waiting on one.
    const stream = createReadStream(file);
    stream.on('error', (error) => {
        console.error(`contrast: could not serve ${path}: ${error.message}`);
        response.destroy(error);
    });
    response.writeHead(200, { 'Content-Type': types[extname(file)] ?? 'application/octet-stream' });
    stream.pipe(response);
});

await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;

const { entries } = JSON.parse(readFileSync(index, 'utf8'));
// Only this Storybook's own docs pages: a composed one (the Astro site) is not
// in index.json and checks itself.
const pages = Object.values(entries).filter((entry) => entry.type === 'docs');
const browser = await chromium.launch({ args: ['--disable-dev-shm-usage'] });
const failures = [];
let checked = 0;

/** axe's colour-contrast rule on `target`. The a11y panel runs axe on load too, so a clash is retried. */
async function analyze(page, target, attempt = 1) {
    try {
        // A story a docs page shows in its own frame is checked on its own
        // story page; inside the docs page its play may be mid-way.
        // `iframes: false` alone still reads into them, so the frames are left
        // out by name too.
        return await new AxeBuilder({ page })
            .include(target)
            .exclude(`${target} iframe`)
            .options({
                iframes: false,
                runOnly: { type: 'rule', values: ['color-contrast'] },
            })
            .analyze();
    } catch (error) {
        if (attempt < 4 && String(error).includes('Axe is already running')) {
            await page.waitForTimeout(1000 * attempt);

            return analyze(page, target, attempt + 1);
        }

        throw error;
    }
}

/**
 * Waits until a docs page is what a reader sees: nothing left to load, its
 * fonts in, and the docs page in the asked theme. The check is synchronous:
 * Playwright takes a promise from an async one as done at once.
 */
async function settle(page, appearance) {
    const theme = appearance === 'dark' ? 'ink' : 'paper';

    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
    await page
        .waitForFunction(
            (expected) => document.fonts.status === 'loaded' && document.documentElement.dataset.docsTheme === expected,
            theme,
            { timeout: 15_000 },
        )
        .catch(() => {});
    // Late renders settle.
    await page.waitForTimeout(900);
}

/**
 * The stylesheets a page links to but did not get. Read without them, its
 * text is the browser's black. Chromium gives a failed one an empty sheet
 * whose rules cannot be read, not a null one.
 */
function missingStyles(page) {
    return page.evaluate(() =>
        [...document.querySelectorAll('link[rel="stylesheet"]')]
            .filter((link) => {
                try {
                    return link.sheet === null || link.sheet.cssRules.length === 0;
                } catch {
                    return true;
                }
            })
            .map((link) => link.href),
    );
}

async function check(entry, appearance) {
    // axe's Playwright wrapper needs a page in a context of its own.
    // Reduced motion, so popovers and dialogs are read open, not mid-fade.
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const mode = 'docs';
    const target = '#storybook-docs';

    try {
        // The release run once read Foundations/Colors without the app's
        // stylesheet: its request had failed, and every line came out black
        // on Storybook's ground. A page without its styles is opened again,
        // and one that never gets them did not load: it says nothing about
        // contrast.
        let missing = [];

        for (let attempt = 1; attempt <= 3; attempt += 1) {
            await page.goto(`${base}/iframe.html?id=${entry.id}&viewMode=${mode}&globals=appearance:${appearance}`, {
                waitUntil: 'load',
                timeout: 30_000,
            });
            await page.waitForSelector(`${target} *`, { timeout: 15_000 }).catch(() => {});
            missing = await missingStyles(page);

            if (missing.length === 0) {
                break;
            }
        }

        if (missing.length > 0) {
            throw new Error(`the stylesheet did not load: ${missing.join(', ')}`);
        }

        await settle(page, appearance);

        const result = await analyze(page, target);

        for (const violation of result.violations) {
            for (const node of violation.nodes) {
                failures.push({
                    page: `${entry.title} › ${entry.name}`,
                    id: entry.id,
                    mode,
                    appearance,
                    target: node.target.join(' '),
                    html: node.html.slice(0, 160),
                    summary: node.failureSummary?.split('\n').slice(1).join(' ').trim(),
                });
            }
        }
    } catch (error) {
        failures.push({ page: `${entry.title} › ${entry.name}`, id: entry.id, mode, appearance, error: String(error).slice(0, 200) });
    } finally {
        checked += 1;
        await context.close();
    }
}

const queue = pages.flatMap((entry) => [
    [entry, 'light'],
    [entry, 'dark'],
]);
const workers = Array.from({ length: 6 }, async () => {
    while (queue.length > 0) {
        const [entry, appearance] = queue.shift();
        await check(entry, appearance);
    }
});

await Promise.all(workers);
await browser.close();
server.close();

writeFileSync(join(root, 'contrast.json'), `${JSON.stringify(failures, null, 1)}\n`);

const unreadable = failures.filter((failure) => !failure.error);
const broken = failures.filter((failure) => failure.error);

console.log(`contrast: ${checked} pages checked (${pages.length} in Paper and in Ink), ${unreadable.length} unreadable elements, ${broken.length} pages that did not load.`);

for (const failure of failures.slice(0, 40)) {
    console.log(`- ${failure.page} [${failure.mode}, ${failure.appearance === 'dark' ? 'Ink' : 'Paper'}] ${failure.target ?? ''} ${failure.summary ?? failure.error}`);
}

process.exit(failures.length > 0 ? 1 : 0);
