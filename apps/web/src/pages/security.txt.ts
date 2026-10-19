import type { APIRoute } from 'astro';
import { absolute, repository } from '../seo/site';

// How to report a security problem (RFC 9116), served at
// /.well-known/security.txt by nginx. The same private GitHub report as
// SECURITY.md. Expires is a year less a day from the build, and every
// release builds it again.
export const GET: APIRoute = ({ site }) => {
    const expires = new Date(Date.now() + 364 * 24 * 60 * 60 * 1000);
    expires.setUTCHours(0, 0, 0, 0);

    return new Response(
        [
            `Contact: ${repository}/security/advisories/new`,
            `Expires: ${expires.toISOString()}`,
            `Policy: ${repository}/blob/main/SECURITY.md`,
            'Preferred-Languages: en',
            `Canonical: ${absolute(site, '/.well-known/security.txt')}`,
            '',
        ].join('\n'),
        { headers: { 'Content-Type': 'text/plain; charset=utf-8' } },
    );
};
