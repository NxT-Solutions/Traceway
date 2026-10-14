import type { APIRoute } from 'astro';
import { legalPages } from '../legal';
import { absolute } from '../seo/site';

// The home page, the pages beside it, and the day they were built.
const pages = ['/', '/support', ...legalPages.map((page) => page.path)];

export const GET: APIRoute = ({ site }) =>
    new Response(
        `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages
    .map(
        (page) => `  <url>
    <loc>${absolute(site, page)}</loc>
    <lastmod>${new Date().toISOString().slice(0, 10)}</lastmod>
  </url>`,
    )
    .join('\n')}
</urlset>
`,
        { headers: { 'Content-Type': 'application/xml; charset=utf-8' } },
    );
