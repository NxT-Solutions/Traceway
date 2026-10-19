import type { APIRoute } from 'astro';
import { indexNowKey } from '../seo/site';

// Proves to IndexNow that the list of changed pages after a release comes
// from this site.
export const GET: APIRoute = () =>
    new Response(`${indexNowKey}\n`, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
