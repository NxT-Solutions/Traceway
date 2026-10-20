// Builds the Traceway design system for Claude from Storybook.
//
// Run `bun run design-system` (it builds Storybook first). It reads the built
// storybook-static/index.json and writes design-system/project/, the files of
// the Design System artifact in Claude:
//
// - tokens.json, README.md, the cover and the brand assets, from
//   resources/design-system/ (tokens are checked against app.css and
//   planner.css, and the build fails when one has drifted);
// - one component card per story file under UI, Components, Planner and Brand,
//   rendered live from the stories by components/bundle.js;
// - React 19 as two classic scripts in components/lib, since the app uses it.
//
// design-system/publish.json lists what to send and what to upload. The
// `design-system` skill (.agents/skills/design-system) says how.

import { execSync } from 'node:child_process';
import {
    copyFileSync,
    existsSync,
    mkdirSync,
    readdirSync,
    readFileSync,
    renameSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { build } from 'vite';

const app = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const source = join(app, 'resources/design-system');
const out = join(app, 'design-system');
const project = join(out, 'project');
const components = join(project, 'components');

const NAMESPACE = 'Traceway';
const STORYBOOK_URL =
    process.env.STORYBOOK_URL ?? 'https://traceway.online/storybook/app/';
// The story groups that make the design system. Pages, Site and Garmin stay in
// Storybook: they are whole screens, linked from the README.
const GROUPS = ['UI', 'Components', 'Planner', 'Brand'];

const fail = (message) => {
    console.error(`design-system: ${message}`);
    process.exit(1);
};

/** Every file under `dir`, as paths relative to it with forward slashes. */
const filesIn = (dir) =>
    readdirSync(dir, { recursive: true, withFileTypes: true })
        .filter((entry) => entry.isFile())
        .map((entry) =>
            relative(dir, join(entry.parentPath, entry.name)).replaceAll('\\', '/'),
        );

const write = (path, text) => {
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, text);
};

// 1. Tokens: tokens.json is the source, with its usage notes; app.css and
//    planner.css must still say the same values.

function customProperties(css, selector) {
    const start = css.indexOf(`${selector}{`) >= 0
        ? css.indexOf(`${selector}{`)
        : css.indexOf(`${selector} {`);

    if (start < 0) {
        fail(`no ${selector} block`);
    }

    const block = css.slice(start, css.indexOf('}', start));
    const values = {};

    for (const match of block.matchAll(/--([\w-]+):\s*([^;]+);/g)) {
        values[match[1]] = match[2].trim();
    }

    return values;
}

const normal = (value) =>
    String(value).toLowerCase().replace(/\s+/g, ' ').trim();

function checkTokens(tokens) {
    const appCss = readFileSync(join(app, 'resources/css/app.css'), 'utf8');
    const plannerCss = readFileSync(
        join(app, 'resources/css/planner.css'),
        'utf8',
    );
    const appLight = customProperties(appCss, ':root');
    const appDark = customProperties(appCss, '.dark');
    const plannerDark = customProperties(plannerCss, '.tw-planner');
    const plannerLight = {
        ...plannerDark,
        ...customProperties(plannerCss, '.tw-planner[data-theme="light"]'),
    };
    const drift = [];

    for (const token of tokens.color.tokens) {
        const light =
            typeof token.value === 'string' ? token.value : token.value.light;
        const dark =
            typeof token.value === 'string'
                ? token.value
                : (token.value.dark ?? light);
        const name = token.name === 'planner-muted' ? 'muted' : token.name;
        const [cssLight, cssDark] =
            token.name in appLight
                ? [appLight[name], appDark[name]]
                : name in plannerDark
                  ? [plannerLight[name], plannerDark[name]]
                  : [undefined, undefined];

        if (cssLight === undefined) {
            continue;
        }

        for (const [theme, ours, theirs] of [
            ['Paper', light, cssLight],
            ['Ink', dark, cssDark],
        ]) {
            // planner.css draws no road casing on Ink; tokens.json says
            // transparent, since a colour token needs a colour.
            if (theirs === 'none') {
                continue;
            }

            if (normal(ours) !== normal(theirs)) {
                drift.push(`${token.name} (${theme}): tokens.json ${ours}, CSS ${theirs}`);
            }
        }
    }

    for (const [token, property] of [
        ['radius-lg', 'radius'],
        ['radius-card', 'radius-card'],
    ]) {
        const ours = tokens.radius.tokens.find((t) => t.name === token)?.value;

        if (normal(ours) !== normal(appLight[property])) {
            drift.push(`${token}: tokens.json ${ours}, app.css ${appLight[property]}`);
        }
    }

    if (drift.length > 0) {
        fail(
            `resources/design-system/tokens.json no longer matches the CSS. Change the token, or the CSS back:\n  ${drift.join('\n  ')}`,
        );
    }
}

// 2. The cards: one per story file in GROUPS, named after its title.

function cardsFromStorybook() {
    const index = join(app, 'storybook-static/index.json');

    if (!existsSync(index)) {
        fail('storybook-static/index.json is missing. Run `bun run build-storybook` first.');
    }

    const { entries } = JSON.parse(readFileSync(index, 'utf8'));
    const cards = new Map();

    for (const entry of Object.values(entries)) {
        const [group, ...rest] = entry.title.split('/');

        if (entry.type !== 'story' || !GROUPS.includes(group)) {
            continue;
        }

        const words = (rest.length > 0 ? rest : [group])
            .join(' ')
            .split(/[^A-Za-z0-9]+/)
            .filter(Boolean)
            .map((word) => word[0].toUpperCase() + word.slice(1));
        const name =
            (group === 'Planner' ? 'Planner' : '') + words.join('');
        const card = cards.get(entry.importPath) ?? {
            name,
            group: rest.length > 1 ? `${group}/${rest[0]}` : group,
            title: entry.title,
            importPath: entry.importPath,
            componentPath: entry.componentPath,
            firstStory: entry.id,
            stories: [],
        };

        card.stories.push(entry.name);
        cards.set(entry.importPath, card);
    }

    const names = new Set();

    for (const card of cards.values()) {
        if (names.has(card.name)) {
            fail(`two story files make the card ${card.name}. Give one a different title.`);
        }

        names.add(card.name);
    }

    return [...cards.values()].sort((a, b) =>
        `${GROUPS.indexOf(a.group.split('/')[0])}${a.title}`.localeCompare(
            `${GROUPS.indexOf(b.group.split('/')[0])}${b.title}`,
        ),
    );
}

// 3. Bundles: React 19 as globals, then the stories reading them.

const globals = {
    react: 'React',
    'react/jsx-runtime': 'React',
    'react/jsx-dev-runtime': 'React',
    'react-dom': 'ReactDOM',
    'react-dom/client': 'ReactDOM',
};

/**
 * Points `react` and `react-dom` at the page's globals. A plain external is not enough: packages that are still
 * CommonJS `require('react')`, which an IIFE build would leave as is.
 */
const fromGlobals = (names) => ({
    name: 'traceway:react-globals',
    enforce: 'pre',
    resolveId: (id) => (names.includes(id) ? `\0global:${id}` : null),
    load: (id) =>
        id.startsWith('\0global:')
            ? `module.exports = window.${globals[id.slice(8)]};`
            : null,
});

const production = {
    'process.env.NODE_ENV': JSON.stringify('production'),
};

async function library(name, entryCode, from) {
    const entry = join(out, `.${name}.js`);
    writeFileSync(entry, entryCode);

    await build({
        configFile: false,
        logLevel: 'warn',
        publicDir: false,
        plugins: [fromGlobals(from)],
        define: production,
        build: {
            outDir: join(components, 'lib'),
            emptyOutDir: false,
            minify: true,
            lib: {
                entry,
                name: name === 'react' ? 'React' : 'ReactDOM',
                formats: ['iife'],
                fileName: () => `${name}.js`,
            },
            rolldownOptions: { output: { exports: 'default' } },
        },
    });
}

async function bundle(cards) {
    const entry = join(out, 'entry.tsx');
    const runtime = relative(out, join(app, '.storybook/design-system/runtime')).replaceAll('\\', '/');
    const imports = cards
        .map((card, i) => `import * as s${i} from '${join(app, card.importPath)}';`)
        .join('\n');
    const list = cards
        .map((card, i) => `{ name: ${JSON.stringify(card.name)}, module: s${i} }`)
        .join(',\n    ');

    writeFileSync(
        entry,
        `${imports}\nimport { mount } from './${runtime}';\n\nmount([\n    ${list},\n]);\n`,
    );

    await build({
        configFile: false,
        logLevel: 'warn',
        publicDir: false,
        root: app,
        plugins: [fromGlobals(Object.keys(globals)), react(), tailwindcss()],
        resolve: {
            alias: { '@': join(app, 'resources/js') },
        },
        define: {
            ...production,
            'import.meta.env.VITE_APP_NAME': JSON.stringify('Traceway'),
        },
        build: {
            outDir: components,
            emptyOutDir: false,
            minify: true,
            cssCodeSplit: false,
            assetsInlineLimit: Number.POSITIVE_INFINITY,
            lib: {
                entry,
                name: `${NAMESPACE}Bundle`,
                formats: ['iife'],
                fileName: () => 'bundle.js',
                cssFileName: 'bundle',
            },
        },
    });

    // Consumers inline these scripts, so no literal end tag or comment opener.
    // The design system also refuses a literal U+FFFD, which some libraries
    // keep in their strings; the escape means the same.
    for (const file of [
        join(components, 'bundle.js'),
        join(components, 'lib/react.js'),
        join(components, 'lib/react-dom.js'),
    ]) {
        const text = readFileSync(file, 'utf8')
            .replaceAll('</script', '<\\/script')
            .replaceAll('<!--', '\\x3C!--')
            .replaceAll('�', '\\uFFFD');
        const header =
            file.endsWith('bundle.js')
                ? `/* @ds-bundle: ${JSON.stringify({ format: 4, namespace: NAMESPACE, components: cards.map((card) => ({ name: card.name })) })} */\n`
                : '';

        writeFileSync(file, header + text);
    }

    for (const file of readdirSync(components)) {
        if (file.endsWith('.css') && file !== 'bundle.css') {
            renameSync(join(components, file), join(components, 'bundle.css'));
        }
    }
}

// 4. Card files: a live preview and the guidelines from the story's own docs.

function docsOf(importPath) {
    const text = readFileSync(join(app, importPath), 'utf8');
    const match = /\/\*\*([\s\S]*?)\*\/\s*const meta/.exec(text);

    return match
        ? match[1]
              .split('\n')
              .map((line) => line.replace(/^\s*\* ?/, ''))
              .join('\n')
              .trim()
        : '';
}

function writeCards(cards) {
    for (const card of cards) {
        const dir = join(components, card.name);
        const height = Math.min(1600, 96 + card.stories.length * 120);

        write(
            join(dir, 'preview.html'),
            `<!-- @dsCard group="${card.group}" height=${height} subtitle="${card.stories.join(' / ').slice(0, 120)}" -->
<!doctype html>
<html><head><meta charset="utf-8"><style>html,body{margin:0;background:var(--background);color:var(--foreground)}</style></head>
<body><div id="root"></div>
<script>window.${NAMESPACE}.preview(document.getElementById('root'), ${JSON.stringify(card.name)});</script>
</body></html>
`,
        );

        const storyUrl = `${STORYBOOK_URL}?path=/story/${card.firstStory}`;

        write(
            join(dir, 'README.md'),
            `# ${card.name}

${docsOf(card.importPath) || `The ${card.title} stories.`}

## Where it lives

- Stories: \`apps/app/${card.importPath.replace(/^\.\//, '')}\` (${card.stories.join(', ')})
${card.componentPath ? `- Component: \`apps/app/${card.componentPath.replace(/^\.\//, '')}\`\n` : ''}- Storybook: ${storyUrl}

This card is built from those stories. Change the story or the component, not this file.
`,
        );
    }
}

// 5. Everything else the system holds.

function copyBrand(tokens) {
    const sha = (() => {
        if (process.env.GITHUB_SHA) {
            return process.env.GITHUB_SHA.slice(0, 7);
        }

        try {
            return execSync('git rev-parse --short HEAD', {
                cwd: app,
                stdio: ['ignore', 'pipe', 'ignore'],
            })
                .toString()
                .trim();
        } catch {
            // DDEV cannot read a worktree's git folder.
            return 'local';
        }
    })();

    tokens.meta = {
        source: 'storybook',
        repo: 'NxT-Solutions/Traceway',
        ref: sha,
        package: 'apps/app',
        storybook: STORYBOOK_URL,
        synced: new Date().toISOString().slice(0, 10),
    };
    write(join(project, 'tokens.json'), `${JSON.stringify(tokens, null, 1)}\n`);

    write(
        join(project, 'README.md'),
        `${readFileSync(join(source, 'README.md'), 'utf8').trimEnd()}

## Storybook

Every component, page, the content site and the Garmin screens are in Traceway's Storybook: ${STORYBOOK_URL}. The components here are built from those stories; design new work there first, then in the code.
`,
    );

    write(
        join(components, 'Cover/preview.html'),
        readFileSync(join(source, 'cover.html'), 'utf8'),
    );

    for (const weight of [400, 500, 600, 700]) {
        const font = `outfit-latin-${weight}-normal.woff2`;
        mkdirSync(join(project, 'fonts'), { recursive: true });
        copyFileSync(
            join(app, 'node_modules/@fontsource/outfit/files', font),
            join(project, 'fonts', font),
        );
    }

    const assets = [
        ...filesIn(join(source, 'assets')).map((file) => [
            join(source, 'assets', file),
            file,
        ]),
        [join(app, 'public/favicon.svg'), 'Logos/traceway-app-icon.svg'],
        [join(app, 'public/images/share-route.png'), 'Images/share-route.png'],
    ];

    for (const [from, to] of assets) {
        mkdirSync(dirname(join(project, 'assets', to)), { recursive: true });
        copyFileSync(from, join(project, 'assets', to));
    }
}

// 6. What the publish sends: text files as files, pictures as uploads.

function publishList() {
    const files = [];
    const uploads = [];

    for (const file of filesIn(project)) {
        const published = `project/${file}`;

        if (/^assets\/.+\.(svg|png|jpe?g|gif|webp|pdf|mp4|webm)$/i.test(file)) {
            uploads.push(published);
        } else {
            files.push(published);
        }
    }

    write(
        join(out, 'publish.json'),
        `${JSON.stringify({ root: out, files: files.sort(), uploads: uploads.sort() }, null, 1)}\n`,
    );

    return { files, uploads };
}

const tokens = JSON.parse(readFileSync(join(source, 'tokens.json'), 'utf8'));
checkTokens(tokens);

const cards = cardsFromStorybook();
rmSync(out, { recursive: true, force: true });
mkdirSync(components, { recursive: true });

// React itself plus the JSX runtime's two functions, so `react/jsx-runtime`
// can read the same global.
await library(
    'react',
    "import * as React from 'react';\nimport { jsx, jsxs } from 'react/jsx-runtime';\nexport default { ...React, jsx, jsxs };\n",
    [],
);
await library(
    'react-dom',
    "import * as ReactDOM from 'react-dom';\nimport * as client from 'react-dom/client';\nexport default { ...ReactDOM, ...client };\n",
    ['react'],
);
await bundle(cards);
writeCards(cards);
copyBrand(tokens);

for (const file of readdirSync(out)) {
    if (file.startsWith('.') || file === 'entry.tsx') {
        rmSync(join(out, file), { force: true });
    }
}

const { files, uploads } = publishList();
console.log(
    `design-system: ${cards.length} cards, ${files.length} files and ${uploads.length} uploads in ${relative(process.cwd(), out)}`,
);
