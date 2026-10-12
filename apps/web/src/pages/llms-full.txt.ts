import type { APIRoute } from 'astro';
import { markdown } from '../seo/home';
import { llms } from '../seo/llms';

// The index and the whole home page in one file, for tools that read once.
export const GET: APIRoute = ({ site }) =>
    new Response(`${llms(site)}\n---\n\n${markdown(site)}`, {
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
