import type { APIRoute } from 'astro';
import { absolute } from '../seo/site';
import { support, supportPage } from '../support';

// The Support page as Markdown, from the same words the page shows.
export const GET: APIRoute = ({ site }) =>
    new Response(
        `# ${supportPage.heading}

${supportPage.paragraphs.join('\n\n')}

- [Donate with PayPal](${support.paypal})
- [Sponsor on GitHub](${support.github})
- [Any amount at paypal.me](${support.paypalMe})
- [Back to Traceway](${absolute(site, '/')})
`,
        { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } },
    );
