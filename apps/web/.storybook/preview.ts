// First, before anything reads storage: under /storybook/ stories get storage of their own.
import './own-storage';
// The site's fonts, as its layouts load them: Outfit 400 to 600 with a metric-matched fallback
// (fonts.css), and 700 for the error pages (ErrorPage.astro). Each story brings its own
// stylesheets (src/stories/support/Canvas.astro).
import '../src/styles/fonts.css';
import '@fontsource/outfit/700.css';
import type { Preview } from '@storybook-astro/framework';
import Canvas from '../src/stories/support/Canvas.astro';
import type { Sheets } from '../src/stories/support/types';

/** What a story file can set under `parameters`, beyond Storybook's own. */
export interface SiteParameters {
    /** Which page's stylesheets the story gets. Defaults to an inner page's. */
    sheets?: Sheets;
    /** The classes the page puts on <body>, from the story's args. */
    bodyClass?: (args: Record<string, unknown>) => string;
    /**
     * 'ask' shows the cookie question as a first visit would. 'answered' (the default) starts with
     * "no" kept, so the question stays shut until Cookie choice asks again.
     */
    consent?: 'ask' | 'answered';
}

const consentKey = 'traceway.consent';

const preview: Preview = {
    // A docs page for every component, from its stories, props and JSDoc.
    tags: ['autodocs'],
    decorators: [
        (_Story, { args, parameters }) => {
            const site = parameters as SiteParameters;

            return {
                component: Canvas,
                props: {
                    sheets: site.sheets ?? 'page',
                    bodyClass: site.bodyClass?.(args),
                },
            };
        },
    ],
    // Every page asks about counting visits until it is answered. Most stories start answered, so
    // the question does not cover them; the Consent stories ask. The answer is put back after each
    // story, so one story's click never decides the next one.
    beforeEach({ parameters }) {
        try {
            if ((parameters as SiteParameters).consent === 'ask') {
                localStorage.removeItem(consentKey);
            } else {
                localStorage.setItem(consentKey, `no:${Date.now()}`);
            }
        } catch {
            // Without storage the question shows, as on the site.
        }

        return () => {
            try {
                localStorage.removeItem(consentKey);
            } catch {
                // Nothing was kept.
            }
        };
    },
    parameters: {
        layout: 'fullscreen',
        controls: {
            expanded: true,
            sort: 'requiredFirst',
        },
        // axe runs on every story; in `npm run test-storybook` and CI a violation fails it.
        a11y: { test: 'error' },
        docs: {
            toc: { headingSelector: 'h2, h3' },
            // Each story on a docs page gets a frame of its own, so its page styles stay in it.
            story: { inline: false, iframeHeight: 480 },
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
        options: {
            storySort: {
                order: ['Components', 'Layouts', 'Pages'],
            },
        },
    },
};

export default preview;
