// What each page is, in the schema.org words search engines and AI answers
// read. One graph per page: who makes Traceway, the site, and the page. The
// nodes point at each other by @id, so a reader can tell the planner, the
// site, and its maker apart.
import { questions, steps } from './home';
import * as about from './site';

type Site = URL | undefined;
type Node = Record<string, unknown>;

const ref = (site: Site, name: string): string => `${about.absolute(site, '/')}#${name}`;

const organization = (site: Site): Node => ({
    '@type': 'Organization',
    '@id': ref(site, 'org'),
    name: about.publisher.name,
    url: about.absolute(site, '/'),
    logo: {
        '@type': 'ImageObject',
        url: about.absolute(site, '/apple-touch-icon.png'),
        width: 180,
        height: 180,
    },
    address: {
        '@type': 'PostalAddress',
        addressLocality: about.publisher.locality,
        addressCountry: about.publisher.country,
    },
    sameAs: [about.repository],
});

const website = (site: Site): Node => ({
    '@type': 'WebSite',
    '@id': ref(site, 'website'),
    name: about.name,
    url: about.absolute(site, '/'),
    description: about.description,
    inLanguage: 'en',
    publisher: { '@id': ref(site, 'org') },
});

const graph = (nodes: Node[]): string =>
    // A "<" in the text would end the script tag early.
    JSON.stringify({ '@context': 'https://schema.org', '@graph': nodes }).replace(/</g, '\\u003c');

/** The home page: the planner as an app, how it works, and the FAQ. */
export const home = (site: Site, description: string): string =>
    graph([
        organization(site),
        website(site),
        {
            '@type': 'WebApplication',
            '@id': ref(site, 'app'),
            name: about.name,
            url: about.absolute(site, '/dashboard'),
            description,
            image: about.absolute(site, '/og.png'),
            screenshot: about.absolute(site, '/og.png'),
            applicationCategory: 'SportsApplication',
            operatingSystem: 'Web',
            browserRequirements: 'Requires a modern web browser.',
            inLanguage: 'en',
            isAccessibleForFree: true,
            offers: { '@type': 'Offer', price: 0, priceCurrency: 'EUR' },
            featureList: [
                ...about.sports.map((sport) => `${sport} loops`),
                ...about.multisport.map((plan) => `${plan} plans`),
                `Routes for ${about.devices.join(', ')}`,
                'Via points',
                'Turn-by-turn directions on Garmin',
            ],
            softwareHelp: { '@type': 'WebPage', url: about.absolute(site, '/#faq') },
            publisher: { '@id': ref(site, 'org') },
            isPartOf: { '@id': ref(site, 'website') },
            sameAs: [about.repository],
        },
        {
            '@type': 'HowTo',
            '@id': ref(site, 'how'),
            name: 'How to plan a loop route with Traceway',
            step: steps().map(({ name, text }, index) => ({
                '@type': 'HowToStep',
                position: index + 1,
                name,
                text,
                url: about.absolute(site, '/#how'),
            })),
        },
        {
            '@type': 'FAQPage',
            '@id': ref(site, 'faq'),
            mainEntity: questions().map(({ question, answer }) => ({
                '@type': 'Question',
                name: question,
                acceptedAnswer: { '@type': 'Answer', text: answer },
            })),
        },
    ]);

/** A page beside the home page: support, or a legal page. */
export const page = (
    site: Site,
    { title, description, url, updated }: { title: string; description: string; url: string; updated?: string },
): string =>
    graph([
        organization(site),
        website(site),
        {
            '@type': 'WebPage',
            '@id': `${url}#page`,
            url,
            name: title,
            description,
            inLanguage: 'en',
            isPartOf: { '@id': ref(site, 'website') },
            publisher: { '@id': ref(site, 'org') },
            ...(updated ? { dateModified: updated } : {}),
            breadcrumb: {
                '@type': 'BreadcrumbList',
                itemListElement: [
                    { '@type': 'ListItem', position: 1, name: about.name, item: about.absolute(site, '/') },
                    { '@type': 'ListItem', position: 2, name: title, item: url },
                ],
            },
        },
    ]);
