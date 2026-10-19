import type { APIRoute } from 'astro';
import { description, name, title } from '../seo/site';

// What a phone shows when someone adds the site to their home screen: the
// name, the colours, and the icon. Opening it goes to the planner.
export const GET: APIRoute = () =>
    new Response(
        JSON.stringify(
            {
                name: title,
                short_name: name,
                description,
                id: '/',
                start_url: '/dashboard',
                scope: '/',
                display: 'standalone',
                background_color: '#f6f3ea',
                theme_color: '#f6f3ea',
                lang: 'en',
                categories: ['sports', 'navigation', 'health', 'fitness'],
                icons: [
                    { src: '/favicon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
                    { src: '/apple-touch-icon.png', sizes: '180x180', type: 'image/png', purpose: 'any' },
                ],
            },
            null,
            2,
        ),
        { headers: { 'Content-Type': 'application/manifest+json' } },
    );
