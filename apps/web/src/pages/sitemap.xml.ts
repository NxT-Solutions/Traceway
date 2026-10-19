import type { APIRoute } from 'astro';
import { legalPages, legalUpdated } from '../legal';
import { absolute } from '../seo/site';

// The home page and the pages beside it, each with the day it last changed.
// The legal pages say that day themselves; the others change with a build.
const built = new Date().toISOString().slice(0, 10);
const legal = new Date(`${legalUpdated} UTC`).toISOString().slice(0, 10);

const pages = [
    { path: '/', changed: built },
    { path: '/support', changed: built },
    ...legalPages.map((page) => ({ path: page.path, changed: legal })),
];

export const GET: APIRoute = ({ site }) =>
    new Response(
        `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages
    .map(
        ({ path, changed }) => `  <url>
    <loc>${absolute(site, path)}</loc>
    <lastmod>${changed}</lastmod>
  </url>`,
    )
    .join('\n')}
</urlset>
`,
        { headers: { 'Content-Type': 'application/xml; charset=utf-8' } },
    );
