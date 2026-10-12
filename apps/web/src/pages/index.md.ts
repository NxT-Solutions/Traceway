import type { APIRoute } from 'astro';
import { markdown } from '../seo/home';

// The home page as Markdown, at the same address with .md on the end.
export const GET: APIRoute = ({ site }) =>
    new Response(markdown(site), {
        headers: { 'Content-Type': 'text/markdown; charset=utf-8' },
    });
