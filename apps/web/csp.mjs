// Production nginx sends a Content-Security-Policy that runs no inline script,
// style tag, event handler or javascript: link
// (.docker/production/images/configs/nginx/nginx.conf). The build fails when a
// page has one, since nothing else serves the site with that policy before a deploy.
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const pages = (dir) =>
    readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);
        return entry.isDirectory() ? pages(path) : entry.name.endsWith('.html') ? [path] : [];
    });

const problems = [];

for (const page of pages('dist')) {
    const html = readFileSync(page, 'utf8');

    for (const [tag] of html.matchAll(/<[a-z][^>]*>/gi)) {
        const inlineScript = /^<script\b/i.test(tag) && !/\ssrc=/i.test(tag) && !/type="application\/(ld\+)?json"/i.test(tag);

        if (inlineScript || /^<style\b/i.test(tag) || /\son[a-z]+\s*=/i.test(tag) || /=\s*"?\s*javascript:/i.test(tag)) {
            problems.push(`${page}: ${tag.slice(0, 120)}`);
        }
    }
}

if (problems.length > 0) {
    console.error(`The site's Content-Security-Policy would block these:\n${problems.join('\n')}`);
    process.exit(1);
}

console.log('csp-check: no inline scripts, style tags or handlers');
