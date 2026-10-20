import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { type Plugin, transformWithOxc } from 'vite';

/*
 * Runs the site's own component <script>s in Storybook, so the cookie question, the header menu and
 * the home page work in a story as on the site.
 *
 * Astro compiles a component's <script> into a module, and a rendered story asks for it as
 * `<script type="module" src="/abs/path/Consent.astro?astro&type=script&index=0&lang.ts">`.
 * @storybook-astro/framework 1.12.0 serves that module with its TypeScript left in (a SyntaxError)
 * in `storybook dev`, and leaves the source path in the HTML of a static build, where nothing
 * answers it. 1.13.0 fixes both (its changelog, #186 and #187); remove this plugin after moving to
 * it.
 *
 * - Dev and Vitest: the module Astro serves at that URL is compiled to JavaScript.
 * - Build: each script, read from the component's source the way Astro reads it (every <script>
 *   without attributes; is:inline, type and set:html scripts stay in the page as they are), is a
 *   chunk of the build, and the prerendered stories point at it.
 */

// The renderer adds `&sbAstroReload=<n>` so the script runs again on each render.
const scriptQuery = /\?astro&type=script&index=(\d+)&lang\.ts(?:&.*)?$/;
// A virtual module per script in the build, named `<prefix><file>:<index>`.
const virtual = '\0traceway-astro-script:';
const prerendered = 'astro-prerendered-stories.json';

/** The bodies of a component's processed <script>s, in Astro's order (`index=N`). */
export function componentScripts(source: string): string[] {
    const template = source
        .replace(/^---[\s\S]*?\n---/, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<script\b[^>]*\/>/g, '');
    const scripts: string[] = [];

    for (const match of template.matchAll(
        /<script\b([^>]*)>([\s\S]*?)<\/script>/g,
    )) {
        if (match[1].trim() === '') {
            scripts.push(match[2]);
        }
    }

    return scripts;
}

// The script runs for what it does to the page, so the build keeps all of it.
const compile = async (code: string, file: string) => ({
    code: (await transformWithOxc(code, `${file}.ts`, { lang: 'ts' })).code,
    moduleSideEffects: true,
});

/** Every .astro file under a folder. */
const astroFiles = (folder: string): string[] =>
    readdirSync(folder, { withFileTypes: true, recursive: true })
        .filter((entry) => entry.isFile() && entry.name.endsWith('.astro'))
        .map((entry) => join(entry.parentPath, entry.name));

export function astroComponentScripts(options: { src: string }): Plugin[] {
    const chunks = new Map<string, string>();
    let outDir = 'storybook-static';

    return [
        {
            name: 'traceway:astro-scripts-dev',
            apply: 'serve',
            enforce: 'post',
            // Astro loads the script as written; this compiles its TypeScript away.
            async transform(code, id) {
                return scriptQuery.test(id)
                    ? compile(code, id.replace(scriptQuery, ''))
                    : null;
            },
        },
        {
            name: 'traceway:astro-scripts-build',
            apply: 'build',
            enforce: 'pre',
            configResolved(config) {
                outDir = resolve(config.root, config.build.outDir);
            },
            buildStart() {
                for (const file of astroFiles(options.src)) {
                    componentScripts(readFileSync(file, 'utf8')).forEach((_, index) => {
                        const reference = this.emitFile({
                            type: 'chunk',
                            id: `${virtual}${file}:${index}`,
                            name: `${file.split('/').pop()?.replace('.astro', '')}-script`,
                        });

                        chunks.set(
                            `${file}?astro&type=script&index=${index}&lang.ts`,
                            reference,
                        );
                    });
                }
            },
            resolveId(id, importer) {
                if (id.startsWith(virtual)) {
                    return id;
                }

                // A script's own imports resolve from its component's folder.
                if (importer?.startsWith(virtual)) {
                    return this.resolve(
                        id,
                        importer.slice(virtual.length).replace(/:\d+$/, ''),
                        { skipSelf: true },
                    );
                }

                return null;
            },
            async load(id) {
                if (!id.startsWith(virtual)) {
                    return null;
                }

                const [, file, index] = id.match(/^\0[^:]+:(.+):(\d+)$/) ?? [];
                const script = componentScripts(readFileSync(file, 'utf8'))[
                    Number(index)
                ];

                return script === undefined ? null : compile(script, file);
            },
            generateBundle() {
                for (const [source, reference] of chunks) {
                    chunks.set(source, this.getFileName(reference));
                }
            },
            // After the framework has written the prerendered stories (in writeBundle).
            closeBundle() {
                const file = join(outDir, prerendered);
                let stories: Record<string, string>;

                try {
                    stories = JSON.parse(readFileSync(file, 'utf8'));
                } catch {
                    return;
                }

                for (const [id, html] of Object.entries(stories)) {
                    let next = html;

                    // Relative, so it holds wherever this Storybook is served, such as /site/.
                    for (const [source, built] of chunks) {
                        next = next.split(`src="${source}"`).join(`src="./${built}"`);
                    }

                    stories[id] = next;
                }

                writeFileSync(file, JSON.stringify(stories));
            },
        },
    ];
}
