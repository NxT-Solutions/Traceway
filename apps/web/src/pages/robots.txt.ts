import type { APIRoute } from 'astro';
import { absolute, privatePaths } from '../seo/site';

// Search engines and AI tools are welcome on the public page. They are named
// so the welcome is explicit. The planner and shared routes stay private.
const crawlers = [
    '*',
    'Googlebot',
    'Google-Extended',
    'Bingbot',
    'Applebot',
    'Applebot-Extended',
    'DuckDuckBot',
    'GPTBot',
    'OAI-SearchBot',
    'ChatGPT-User',
    'ClaudeBot',
    'Claude-SearchBot',
    'Claude-User',
    'PerplexityBot',
    'Perplexity-User',
    'Grok',
    'xAI-Bot',
    'meta-externalagent',
    'Amazonbot',
    'MistralAI-User',
    'CCBot',
];

export const GET: APIRoute = ({ site }) =>
    new Response(
        [
            ...crawlers.map((crawler) => `User-agent: ${crawler}`),
            'Allow: /',
            ...privatePaths.map((path) => `Disallow: ${path}`),
            '',
            `Sitemap: ${absolute(site, '/sitemap.xml')}`,
            '',
        ].join('\n'),
        { headers: { 'Content-Type': 'text/plain; charset=utf-8' } },
    );
