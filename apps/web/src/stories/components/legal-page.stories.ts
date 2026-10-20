import { expect, within } from 'storybook/test';
import LegalPage from '../../components/LegalPage.astro';
import { privacy } from '../../legal';
import { sampleLegalPage, shortLegalPage } from '../support/fixtures';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * A legal page, in the site's layout (Base): a kicker, the heading, the day it last changed, and
 * the text. Text holds only [links](/path) and **bold** (src/legal.ts). The real pages are under
 * Pages. In `npm run storybook`, edit `page` under Controls to try other words.
 */
const meta = {
    title: 'Components/LegalPage',
    component: LegalPage,
    args: { page: sampleLegalPage },
    argTypes: {
        page: {
            description:
                'The words: path, title, description, heading, intro, and sections with paragraphs, items and text after them.',
            control: 'object',
        },
    },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta.args>;

/** Every part a legal page can hold. */
export const Sample: Story = {
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(page.getByRole('heading', { level: 1, name: 'Sample legal page' }));
        await shows(page.getByText(/^Last updated /));
        // Markdown-style links and bold become HTML.
        await expect(page.getByRole('link', { name: 'privacy policy' })).toHaveAttribute(
            'href',
            '/privacy',
        );
        await shows(page.getByText('Bold', { selector: 'strong' }));
        const prose = within(canvasElement.querySelector('.prose') as HTMLElement);

        await expect(prose.getAllByRole('heading', { level: 2 })).toHaveLength(3);
        await expect(prose.getAllByRole('listitem')).toHaveLength(3);
    },
};

/** One section and nothing else. */
export const Short: Story = { args: { page: shortLegalPage } };

/** The longest real one. */
export const PrivacyPolicy: Story = {
    name: 'Privacy policy',
    args: { page: privacy },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
