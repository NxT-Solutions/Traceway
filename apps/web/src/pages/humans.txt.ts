import type { APIRoute } from 'astro';
import { name, repository } from '../seo/site';

// Who makes the site (humanstxt.org).
export const GET: APIRoute = () =>
    new Response(
        [
            '/* TEAM */',
            `${name} is made by NxT-Solutions in Westerlo, Belgium.`,
            `Code: ${repository}`,
            '',
            '/* THANKS */',
            'Map data: OpenStreetMap contributors, https://www.openstreetmap.org/copyright',
            'Routing: Valhalla',
            'Places: Nominatim',
            'Map tiles: OpenFreeMap',
            '',
            '/* SITE */',
            'Standards: HTML, CSS, JavaScript',
            'Components: Astro, Laravel, React, MapLibre',
            '',
        ].join('\n'),
        { headers: { 'Content-Type': 'text/plain; charset=utf-8' } },
    );
