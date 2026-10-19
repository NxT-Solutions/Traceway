// The browser suites reach only the servers they start: the planner, the
// content site, and valhalla-stub.php, which stands in for the router, the
// tiles, and Nominatim. The map asks tiles.openfreemap.org for its basemap
// straight from the browser, so those requests are answered by the stub's
// mock basemap: a plain background with a few named towns. Every other
// outside request is blocked and named once, so a test never waits on a
// public server and a new one shows up in the log.

const stub = process.env.STUB_URL || 'http://127.0.0.1:8002';
const localHosts = new Set(['127.0.0.1', 'localhost', '[::1]', 'traceway.ddev.site']);

for (const url of [process.env.BASE_URL, process.env.CONTENT_URL]) {
    if (url) {
        localHosts.add(new URL(url).hostname);
    }
}

const blockedHosts = new Set();

// The mock basemap never changes, so each style and tile is asked for once.
const basemap = new Map();

/** Where the stub answers an OpenFreeMap basemap request, or null. */
function basemapOnStub(url) {
    if (url.hostname !== 'tiles.openfreemap.org') {
        return null;
    }

    if (url.pathname.startsWith('/styles/')) {
        return `${stub}${url.pathname}`;
    }

    if (url.pathname.startsWith('/mock/')) {
        return `${stub}/basemap/${url.pathname.slice('/mock/'.length)}`;
    }

    return null;
}

function fromStub(url) {
    if (!basemap.has(url)) {
        const answer = fetch(url, { signal: AbortSignal.timeout(10000) }).then(async (response) => ({
            status: response.status,
            contentType: response.headers.get('content-type') ?? 'application/octet-stream',
            body: Buffer.from(await response.arrayBuffer()),
        }));
        answer.catch(() => basemap.delete(url));
        basemap.set(url, answer);
    }

    return basemap.get(url);
}

async function answer(route) {
    const url = new URL(route.request().url());

    if (!url.protocol.startsWith('http') || localHosts.has(url.hostname)) {
        await route.continue();

        return;
    }

    const onStub = basemapOnStub(url);

    if (onStub !== null) {
        const { status, contentType, body } = await fromStub(onStub);
        // The page asks across origins, so the answer says it may read it.
        await route.fulfill({ status, contentType, body, headers: { 'access-control-allow-origin': '*' } });

        return;
    }

    if (!blockedHosts.has(url.hostname)) {
        blockedHosts.add(url.hostname);
        console.log(`· blocked ${url.hostname}, an outside server (${url.pathname})`);
    }

    await route.abort('blockedbyclient');
}

/**
 * Mock the outside servers for a page or a browser context, before it opens
 * its first page. A route a test adds later still runs first.
 */
export async function mockOutsideServers(target) {
    await target.route('**/*', async (route) => {
        try {
            await answer(route);
        } catch (error) {
            // A page that navigated or closed no longer wants the answer.
            await route.abort('failed').catch(() => {});

            if (!/closed|disposed|already handled/i.test(String(error))) {
                console.log(`· mock ${route.request().url()} failed: ${error.message}`);
            }
        }
    });
}
