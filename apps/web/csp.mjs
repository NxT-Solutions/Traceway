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
// can allow, on a script file from anywhere but /_astro or one it cannot
// rewrite, and on a script or style tag inside an inline <svg>.
import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

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

for (const page of pages('dist')) {
    const files = [];
    let html = readFileSync(page, 'utf8').replace(
        /<script\b([^>]*)\ssrc="([^"]*)"([^>]*)><\/script>/gi,
        (tag, before, src, after) => {
            if (!/\btype="module"/i.test(before + after) || !/^\/_astro\/[\w.-]+\.js$/.test(src)) {
                problems.push(`${page}: ${tag.slice(0, 120)}`);
                return tag;
            }

            files.push(src);
            return files.length === 1 ? '<script data-loader></script>' : '';
        },
    );

    if (files.length > 0) {
        html = html.replace('<script data-loader></script>', `<script type="module">${loader(files)}</script>`);
    }

    // A script file left in the page, written some other way, would be blocked without a word.
    for (const [tag] of html.matchAll(/<script\b[^>]*\ssrc\s*=[^>]*>/gi)) {
        problems.push(`${page}: ${tag.slice(0, 120)}`);
    }

    // Inside an inline <svg>, the browser decodes the text of a script or style before it hashes it.
    for (const [svg] of html.matchAll(/<svg\b[\s\S]*?<\/svg>/gi)) {
        if (/<(script|style)\b/i.test(svg)) {
            problems.push(`${page}: a script or style tag inside an inline <svg>`);
        }
    }

    for (const [, attributes, body] of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        if (!/type="application\/(ld\+)?json"/i.test(attributes)) {
            scripts.add(hash(body));
        }
    }

    for (const [, body] of html.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)) {
        styles.add(hash(body));
    }

    for (const [tag] of html.matchAll(/<[a-z][^>]*>/gi)) {
        if (/\son[a-z]+\s*=/i.test(tag) || /=\s*"?\s*javascript:/i.test(tag)) {
            problems.push(`${page}: ${tag.slice(0, 120)}`);
        }
    }

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
