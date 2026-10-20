import { expect, within } from 'storybook/test';
import Base from '../../layouts/Base.astro';
import SampleMain from '../support/SampleMain.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/** Base's props (src/layouts/Base.astro), and the page in its slot. */
interface BaseArgs {
    title: string;
    description: string;
    markdown?: string;
    updated?: string;
    slots?: Record<string, unknown>;
}

/**
 * The layout of every page but the home page: the cookie question, the header, the page, and the
 * footer, with the page's title, description, canonical address and structured data in its head.
 * Support and the legal pages use it. The page goes in its slot.
 */
const meta = {
    title: 'Layouts/Base',
    component: Base,
    args: {
        title: 'Sample',
        description: 'A page in the site’s layout, for Storybook.',
        slots: { default: { component: SampleMain } },
    },
    argTypes: {
        title: {
            description:
                'The page’s title. " · Traceway" follows it, unless it is "Traceway".',
            control: 'text',
        },
        description: {
            description: 'What search engines and link previews show under the title.',
            control: 'text',
        },
        markdown: {
            description:
                'The page as Markdown for AI tools, such as "/support.md", linked from the head.',
            control: 'text',
        },
        updated: {
            description: 'The day the page last changed, as YYYY-MM-DD, for search engines.',
            control: 'text',
        },
        slots: { table: { disable: true } },
    },
} satisfies Meta<BaseArgs>;

export default meta;

type Story = StoryObj<BaseArgs>;

export const Default: Story = {
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(page.getByRole('banner'));
        await shows(page.getByRole('heading', { level: 1, name: /site’s layout/ }));
        await shows(page.getByRole('contentinfo'));
    },
};

/** With a Markdown twin and a last-changed day, as the legal pages have. */
export const WithMarkdown: Story = {
    name: 'With Markdown and a date',
    args: { markdown: '/sample.md', updated: '2026-10-20' },
    play: async ({ canvasElement }) => {
        await expect(
            canvasElement.querySelector('link[rel="alternate"][type="text/markdown"]'),
        ).toHaveAttribute('href', '/sample.md');
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
