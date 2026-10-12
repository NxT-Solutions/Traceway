import TurndownService from 'turndown';
import body from '../home/body.html?raw';
import { absolute } from './site';

export interface Question {
    question: string;
    answer: string;
}

/** Plain words from a bit of HTML. Strips until nothing changes, so a tag
 * split around another tag cannot survive, then drops any stray bracket. */
const text = (html: string): string => {
    let words = html;
    let before: string;

    do {
        before = words;
        words = words.replace(/<[^<>]*>/g, '');
    } while (words !== before);

    return words.replace(/[<>]/g, '').replace(/\s+/g, ' ').trim();
};

/** The FAQ on the home page, read from the same HTML the page shows. */
export const questions = (): Question[] =>
    [
        ...body.matchAll(
            /<details>\s*<summary>([\s\S]*?)<svg[\s\S]*?<\/summary>\s*<p>([\s\S]*?)<\/p>\s*<\/details>/g,
        ),
    ].map(([, question = '', answer = '']) => ({
        question: text(question),
        answer: text(answer),
    }));

/**
 * The home page as Markdown, for AI tools and anything else that reads text.
 * It comes from the same HTML as the page, so the two never drift.
 */
export const markdown = (site: URL | undefined): string => {
    const html = body
        // Keep words apart that the page sets side by side.
        .replace(/<br\s*\/?>/g, ' ')
        .replace(/<\/span>\s*<span/g, '</span> <span')
        .replace(/<\/b>\s*<span>/g, '</b>: <span>')
        .replace(/<small>/g, ' (<small>')
        .replace(/<\/small>/g, '</small>)');

    const turndown = new TurndownService({
        headingStyle: 'atx',
        bulletListMarker: '-',
    });

    // The map, the animation readout, and the decoration say nothing in text.
    // A rule, not remove(): turndown checks remove() after its own rules.
    turndown.addRule('decoration', {
        filter: (node) =>
            ['svg', 'SVG', 'CANVAS', 'SCRIPT', 'STYLE'].includes(node.nodeName) ||
            node.getAttribute('aria-hidden') === 'true' ||
            node.id === 'hud' ||
            /\b(cue|meta|kicker|num)\b/.test(node.getAttribute('class') ?? ''),
        replacement: () => '',
    });

    turndown.addRule('question', {
        filter: 'details',
        replacement: (_content, node) => {
            const element = node as HTMLElement;
            const summary = element.querySelector('summary');
            const answer = element.querySelector('p');

            return `\n\n### ${text(summary?.innerHTML ?? '')}\n\n${text(answer?.innerHTML ?? '')}\n\n`;
        },
    });

    turndown.addRule('link', {
        filter: 'a',
        replacement: (content, node) => {
            const href = (node as HTMLElement).getAttribute('href') ?? '/';

            return `[${content}](${absolute(site, href.startsWith('#') ? `/${href}` : href)})`;
        },
    });

    const page = turndown
        .turndown(html)
        .replace(/\n{3,}/g, '\n\n')
        .trim();

    return `${page}

## Links

- [Open the planner](${absolute(site, '/dashboard')})
- [Create an account](${absolute(site, '/register')})
- [Summary for AI tools](${absolute(site, '/llms.txt')})

Map data from OpenStreetMap.
`;
};
