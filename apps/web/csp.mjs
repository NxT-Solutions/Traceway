// Production nginx sends a strict Content-Security-Policy
// (.docker/production/images/configs/nginx/nginx.conf): a script or style tag
// runs only when its hash is listed, and 'strict-dynamic' lets the scripts
// those add to the page run too. A page on a CDN cache cannot carry a nonce,
// so the hashes come from the build:
//
// 1. The script files a page names, <script type="module" src>, are added by
//    one small inline loader instead. Its hash allows it, and 'strict-dynamic'
//    allows the script elements it makes. A static or dynamic import would
//    not do: it counts as written in the page, which no hash covers. Trusted
//    Types take each address only from the loader's "scripts" policy, which
//    knows the page's files and nothing else. The files ask for a low
//    priority: the first paint needs none of them, so they wait behind it.
// 2. Every inline script and style tag is hashed into nginx-csp.conf, which
//    the image copies next to nginx.conf.
//
// The build fails on an event handler or a javascript: link, which no hash
// can allow, on a script file from anywhere but /_astro, and on a script or
// style tag inside an inline <svg>. An HTML parser reads the pages, as a
// browser would, so no way of writing a tag slips past.
import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { Parser } from 'htmlparser2';

const pages = (dir) =>
    readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);
        return entry.isDirectory() ? pages(path) : entry.name.endsWith('.html') ? [path] : [];
    });

// The browser hashes the text with its line endings made \n, so this does too.
const hash = (text) =>
    `'sha256-${createHash('sha256').update(text.replace(/\r\n?/g, '\n'), 'utf8').digest('base64')}'`;

const problems = [];
const scripts = new Set();
const styles = new Set();

// The loader each page carries in place of its script files. It runs in the
// browser, written out with toString(), so it is plain code here. When a file
// cannot load, the page drops the js class and shows everything finished, as
// it does without scripts.
function load(files) {
    const root = document.documentElement;
    try {
        const types = window.trustedTypes;
        const policy = types
            ? types.createPolicy('scripts', {
                  createScriptURL: (url) => {
                      if (!files.includes(url)) {
                          throw new TypeError(url);
                      }
                      return url;
                  },
              })
            : null;
        for (const file of files) {
            const script = document.createElement('script');
            script.type = 'module';
            script.fetchPriority = 'low';
            script.onerror = () => root.classList.remove('js');
            script.src = policy ? policy.createScriptURL(file) : file;
            document.head.append(script);
        }
    } catch {
        root.classList.remove('js');
    }
}

const loader = (files) => `(${load})(${JSON.stringify(files)});`;

// The script and style elements of a page, with where each one sits in the
// text and the text inside it as written, plus any attribute no hash allows.
const read = (html) => {
    const elements = [];
    const unsafe = [];
    const open = [];
    let svg = 0;

    const parser = new Parser(
        {
            onopentag(name, attributes) {
                if (name === 'svg') {
                    svg += 1;
                }
                for (const [key, value] of Object.entries(attributes)) {
                    if (key.startsWith('on') || /^[\s\0-\x1f]*javascript:/i.test(value)) {
                        unsafe.push(`<${name} ${key}="${value.slice(0, 80)}">`);
                    }
                }
                if (name === 'script' || name === 'style') {
                    // After an end tag like </script >, the parser counts its ">" as the start of the next tag.
                    const start = html.indexOf('<', parser.startIndex);
                    open.push({ name, attributes, start, text: '', inSvg: svg > 0 });
                }
            },
            ontext(text) {
                const element = open.at(-1);
                if (element) {
                    element.text += text;
                }
            },
            onclosetag(name) {
                if (name === 'svg') {
                    svg -= 1;
                }
                if ((name === 'script' || name === 'style') && open.at(-1)?.name === name) {
                    // An end tag may carry spaces before its ">", as in </script >.
                    elements.push({ ...open.pop(), end: html.indexOf('>', parser.endIndex) + 1 });
                }
            },
        },
        { decodeEntities: true, lowerCaseTags: true, lowerCaseAttributeNames: true },
    );
    parser.write(html);
    parser.end();

    return { elements, unsafe };
};

const isData = (element) => /^application\/(ld\+)?json$/i.test(element.attributes.type ?? '');

for (const page of pages('dist')) {
    let html = readFileSync(page, 'utf8');

    // The script files become one loader, put where the first one was. The
    // others are cut out by where they sit, from the end, so the places of
    // the ones before stay right.
    const files = read(html).elements.filter((element) => element.name === 'script' && 'src' in element.attributes);
    const allowed = files.every(
        ({ attributes }) => attributes.type === 'module' && /^\/_astro\/[\w.-]+\.js$/.test(attributes.src),
    );

    if (files.length > 0 && allowed) {
        const sources = files.map(({ attributes }) => attributes.src);
        for (const [index, file] of [...files.entries()].reverse()) {
            const replacement = index === 0 ? `<script type="module">${loader(sources)}</script>` : '';
            html = html.slice(0, file.start) + replacement + html.slice(file.end);
        }
    }

    const { elements, unsafe } = read(html);

    for (const element of elements) {
        if (element.inSvg) {
            // Inside an inline <svg>, the browser decodes the text before it hashes it.
            problems.push(`${page}: a <${element.name}> inside an inline <svg>`);
        } else if (element.name === 'script' && 'src' in element.attributes) {
            problems.push(`${page}: <script src="${element.attributes.src}"> is not a module from /_astro`);
        } else if (element.name === 'script' && !isData(element)) {
            scripts.add(hash(element.text));
        } else if (element.name === 'style') {
            styles.add(hash(element.text));
        }
    }

    problems.push(...unsafe.map((tag) => `${page}: ${tag}`));

    writeFileSync(page, html);
}

if (problems.length > 0) {
    console.error(`The site's Content-Security-Policy would block these:\n${problems.join('\n')}`);
    process.exit(1);
}

const list = (set) => [...set].sort().join(' ');

writeFileSync(
    'nginx-csp.conf',
    `# Written by apps/web/csp.mjs from the build. The hashes of the scripts and
# styles the site's pages carry inline, for $site_csp in nginx.conf.
map $host $site_script_hashes {
  default "${list(scripts)}";
}

map $host $site_style_hashes {
  default "${list(styles)}";
}
`,
);

console.log(`csp: ${scripts.size} script and ${styles.size} style hashes in nginx-csp.conf`);
