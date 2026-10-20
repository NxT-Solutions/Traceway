// First, before anything reads storage: under /storybook/ stories get storage of their own.
import './own-storage';
import '@fontsource/outfit/400.css';
import '@fontsource/outfit/500.css';
import '@fontsource/outfit/600.css';
import '@fontsource/outfit/700.css';
import '../resources/css/app.css';
import '../resources/css/planner.css';
import './preview.css';
import type { Preview } from '@storybook/react-vite';
import { HttpResponse, http } from 'msw';
import { setupWorker } from 'msw/browser';
import { mswLoader } from 'msw-storybook-addon/csf3';
import { Appearance } from '../resources/js/stories/support/appearance';
import { ThemedDocsContainer } from './docs-container';

// Read once, as the preview loads: a page story's Inertia writes the page's own address into the frame later.
/** The folder Storybook is served from: /storybook/app/ when published, / in dev and the tests. */
const storybookFolder = new URL('.', window.location.href).pathname;
/** Whether this is the published Storybook, on the same site as the planner. */
const published = window.location.pathname.startsWith('/storybook/');

/**
 * Published, Storybook lives at traceway.online/storybook/app/, on the same site as the planner, so a story runs
 * with a signed-in visitor's cookies. A request to this site that no story mocks gets a 503 here and never reaches
 * the real server (which also refuses changes from Storybook pages: RefuseStorybookChanges). Storybook's own files,
 * under the folder this page sits in, and other sites, such as map tiles, go out as usual. Story handlers come
 * before this one, so a mocked request still gets its mock.
 */
function noRealServer() {
    return http.all('*', ({ request }) => {
        const url = new URL(request.url);

        if (
            url.origin !== window.location.origin ||
            url.pathname.startsWith(storybookFolder)
        ) {
            return undefined;
        }

        return HttpResponse.json(
            {
                message:
                    'Storybook has no server: this story does not mock that request.',
            },
            { status: 503 },
        );
    });
}

/** Whether a request would go to the planner on this site, rather than to Storybook's own files or another site. */
function toRealServer(target: string | URL): boolean {
    const url = new URL(target, window.location.href);

    return (
        published &&
        url.origin === window.location.origin &&
        !url.pathname.startsWith(storybookFolder)
    );
}

/**
 * Without the mocked server, a published story's requests would reach the real planner with the visitor's cookies.
 * fetch and XMLHttpRequest refuse them instead. Off Storybook's published address, nothing changes.
 */
function refuseRealServer() {
    const fetchFromNetwork = window.fetch.bind(window);

    window.fetch = (input, init) => {
        const target = input instanceof Request ? input.url : input;

        return toRealServer(target)
            ? Promise.reject(new TypeError('Storybook has no server.'))
            : fetchFromNetwork(input, init);
    };

    const open = XMLHttpRequest.prototype.open;

    XMLHttpRequest.prototype.open = function (
        this: XMLHttpRequest,
        method: string,
        url: string | URL,
        ...rest: [boolean?, string?, string?]
    ) {
        if (toRealServer(url)) {
            throw new TypeError('Storybook has no server.');
        }

        return open.call(this, method, url, ...(rest as [boolean, string?, string?]));
    } as typeof XMLHttpRequest.prototype.open;
}

const preview: Preview = {
    // A docs page for every component, built from its stories, props and
    // JSDoc. A story file opts out with `tags: ['!autodocs']`.
    tags: ['autodocs'],
    // Stories have no server behind them. Mock Service Worker answers what a
    // story asks for in `beforeEach({ msw }) { msw.use(…) }` (helpers in
    // stories/support/server.ts); everything else, such as map tiles, goes out
    // as usual. The worker sits next to iframe.html, so its path is relative
    // for GitHub Pages' /Traceway/ prefix.
    loaders: [
        mswLoader(async () => {
            const worker = setupWorker(noRealServer());

            try {
                await worker.start({
                    onUnhandledRequest: 'bypass',
                    quiet: true,
                    serviceWorker: { url: './mockServiceWorker.js' },
                });
            } catch (error) {
                // A browser that blocks service workers still shows every
                // story; only the ones that need the mocked server go without.
                // Published, nothing then stands between a story and the real
                // planner, so its requests to this site are refused here.
                console.warn('Storybook: the mocked server did not start.', error);
                refuseRealServer();
            }

            return worker;
        }),
    ],
    globalTypes: {
        appearance: {
            description: 'Paper (light) or Ink (dark)',
            toolbar: {
                title: 'Appearance',
                icon: 'mirror',
                items: [
                    { value: 'light', title: 'Paper' },
                    { value: 'dark', title: 'Ink' },
                ],
                dynamicTitle: true,
            },
        },
    },
    // The tests run every story twice, in Paper and in Ink (vitest.config.ts
    // sets STORYBOOK_APPEARANCE for the second run).
    initialGlobals: {
        appearance:
            import.meta.env.STORYBOOK_APPEARANCE === 'dark' ? 'dark' : 'light',
    },
    decorators: [
        (Story, { globals }) => (
            <Appearance
                mode={globals.appearance === 'dark' ? 'dark' : 'light'}
            >
                <Story />
            </Appearance>
        ),
    ],
    parameters: {
        layout: 'padded',
        controls: {
            expanded: true,
            sort: 'requiredFirst',
            matchers: { color: /(background|colou?r)$/i, date: /(At|_at|Date)$/ },
        },
        // axe runs on every story; in `bun run test:storybook` and CI a
        // violation fails the story. A story that cannot pass yet says why
        // with `a11y: { test: 'todo' }`.
        a11y: { test: 'error' },
        docs: {
            toc: { headingSelector: 'h2, h3' },
            container: ThemedDocsContainer,
            codePanel: true,
        },
        backgrounds: {
            options: {
                paper: { name: 'Paper', value: 'oklch(0.97 0.012 95)' },
                ink: { name: 'Ink', value: 'oklch(0.22 0.03 200)' },
                map: { name: 'Map', value: 'oklch(0.955 0.018 100)' },
            },
        },
        options: {
            storySort: {
                order: [
                    'Start here',
                    'Foundations',
                    'Brand',
                    'UI',
                    'Components',
                    'Planner',
                    'Pages',
                    'Site',
                    'Garmin',
                ],
            },
        },
        viewport: {
            options: {
                phone: {
                    name: 'Phone',
                    styles: { width: '390px', height: '844px' },
                },
                tablet: {
                    name: 'Tablet',
                    styles: { width: '820px', height: '1180px' },
                },
                desktop: {
                    name: 'Desktop',
                    styles: { width: '1440px', height: '900px' },
                },
            },
        },
    },
};

export default preview;
