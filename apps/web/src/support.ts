/** Where a donation goes. Traceway is free, and these keep it hosted. */
export const support = {
    paypal: 'https://www.paypal.com/donate/?hosted_button_id=U88M9M4XDFCP8',
    paypalMe: 'https://paypal.me/noahgillard1',
    github: 'https://github.com/sponsors/NoahNxT',
} as const;

/** The Support page, as words. The page and /support.md both read these. */
export const supportPage = {
    title: 'Support',
    description:
        'Traceway is free, and it always will be. Donations pay for the server, the routing engine, and the map.',
    heading: 'Free today. Free for good.',
    paragraphs: [
        'Traceway is free, and it always will be. No ads, no tracking, no paid tier, and no feature held back behind a subscription.',
        'I built it because I love being out there, on the bike, on the trail, on a long run, and I wanted the best route planner I could get without paying the high monthly prices the big apps ask for. So I made one, and I made it for everyone.',
        'Drawing a loop still costs money. A server runs the planner, a routing engine scores every road and path, and the map data from OpenStreetMap has to live somewhere close to it. I pay for all of it, in spare time.',
        'If you enjoy Traceway, a donation helps keep it online. It is never required. Nothing unlocks, and the planner stays the same for everyone.',
    ],
} as const;
