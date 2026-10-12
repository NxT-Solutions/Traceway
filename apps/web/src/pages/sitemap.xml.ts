import type { APIRoute } from 'astro';
import { absolute } from '../seo/site';

// The site is one page. The sitemap says so, with the day it was built.
export const GET: APIRoute = ({ site }) =>
    new Response(
        `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>${absolute(site, '/')}</loc>
    <lastmod>${new Date().toISOString().slice(0, 10)}</lastmod>
  </url>
</urlset>
`,
        { headers: { 'Content-Type': 'application/xml; charset=utf-8' } },
    );
