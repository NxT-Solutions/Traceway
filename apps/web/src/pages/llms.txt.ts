import type { APIRoute } from 'astro';
import { llms } from '../seo/llms';

export const GET: APIRoute = ({ site }) =>
    new Response(llms(site), {
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
