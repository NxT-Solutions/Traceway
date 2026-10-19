import type { APIRoute } from 'astro';
import { absolute, privatePaths } from '../seo/site';

// Search engines, AI tools, and link previews are welcome on the public pages.
// Every crawler follows the * group, so naming one changes nothing for it;
// they are named so the welcome is explicit, each by the token its owner
// documents. The planner and shared routes stay private for all of them.
const crawlers: Record<string, string[]> = {
    'Search engines': [
        'Googlebot',
        'Googlebot-Image',
        'Bingbot',
        'Applebot',
        'DuckDuckBot',
        'YandexBot',
        'Baiduspider',
        'Yeti',
        'SeznamBot',
        'Qwantbot',
        'PetalBot',
        'MojeekBot',
    ],
    'AI search and assistants, fetching a page someone asked about': [
        'OAI-SearchBot',
        'ChatGPT-User',
        'Claude-SearchBot',
        'Claude-User',
        'PerplexityBot',
        'Perplexity-User',
        'DuckAssistBot',
        'MistralAI-User',
        'Meta-ExternalFetcher',
        'GoogleOther',
        'Google-CloudVertexBot',
    ],
    'AI models, learning from the public pages': [
        'Google-Extended',
        'Applebot-Extended',
        'GPTBot',
        'ClaudeBot',
        'meta-externalagent',
        'Amazonbot',
        'CCBot',
        'Bytespider',
        'Grok',
        'xAI-Bot',
    ],
    'Link previews in chats and social posts': [
        'facebookexternalhit',
        'Twitterbot',
        'LinkedInBot',
        'Slackbot',
        'Discordbot',
        'WhatsApp',
        'TelegramBot',
        'Pinterestbot',
        'redditbot',
    ],
};

export const GET: APIRoute = ({ site }) =>
    new Response(
        [
            '# Traceway plans loop routes for every sport. The public pages are open to',
            '# every crawler; the planner and shared routes are private.',
            `# A plain summary for AI tools: ${absolute(site, '/llms.txt')}`,
            '',
            'User-agent: *',
            ...Object.entries(crawlers).flatMap(([group, names]) => [
                `# ${group}`,
                ...names.map((crawler) => `User-agent: ${crawler}`),
            ]),
            'Allow: /',
            ...privatePaths.map((path) => `Disallow: ${path}`),
            '',
            `Sitemap: ${absolute(site, '/sitemap.xml')}`,
            '',
        ].join('\n'),
        { headers: { 'Content-Type': 'text/plain; charset=utf-8' } },
    );
