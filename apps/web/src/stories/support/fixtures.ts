import type { LegalPage } from '../../legal';

/** A legal page with every part one can hold: an intro, paragraphs, a list, and text after it. */
export const sampleLegalPage: LegalPage = {
    path: '/sample',
    title: 'Sample legal page',
    description: 'Every part a legal page can hold, for Storybook.',
    heading: 'Sample legal page',
    intro: [
        'This page shows every part a legal page can hold. The real ones are the [privacy policy](/privacy), the [terms of use](/terms), [cookies and storage](/cookies), and the [legal notice](/legal).',
    ],
    sections: [
        {
            heading: 'Paragraphs',
            paragraphs: [
                'Traceway plans routes for any sport and sends them to your watch or bike computer. **Bold** marks the words that matter most.',
                'A second paragraph follows the first, with the same space between them.',
            ],
        },
        {
            heading: 'A list',
            paragraphs: ['A short line leads into the list.'],
            items: [
                'Run, trail run, walk, and hike.',
                'Road, gravel, and mountain bike.',
                'Map data from [OpenStreetMap](https://www.openstreetmap.org/copyright).',
            ],
            after: ['A paragraph can follow the list, too.'],
        },
        {
            heading: 'Questions',
            paragraphs: [
                'Write to [privacy@nxt-solutions.com](mailto:privacy@nxt-solutions.com).',
            ],
        },
    ],
};

/** The shortest legal page: a heading, one line, and one section. */
export const shortLegalPage: LegalPage = {
    path: '/short',
    title: 'Short legal page',
    description: 'A legal page with one section.',
    heading: 'Short legal page',
    intro: ['One line of introduction.'],
    sections: [
        {
            heading: 'The only section',
            paragraphs: ['Traceway is free, and it always will be.'],
        },
    ],
};
