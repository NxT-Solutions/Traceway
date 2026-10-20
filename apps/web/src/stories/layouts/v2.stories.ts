import { expect, within } from 'storybook/test';
import SiteFooter from '../../components/SiteFooter.astro';
import SiteHeader from '../../components/SiteHeader.astro';
import V2 from '../../layouts/V2.astro';
import SampleHero from '../support/SampleHero.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/** V2's props (src/layouts/V2.astro), and what goes in its slot. */
interface V2Args {
    title?: string;
    description?: string;
    slots?: Record<string, unknown>;
}

/**
 * The home page's layout: the cookie question, Lenis for smooth scrolling, and the home page's
 * styles (v2.css). Unlike Base it brings no header or footer; the home page puts its own in the
 * slot, with `home` set. The real home page is under Pages.
 */
const meta = {
    title: 'Layouts/V2',
    component: V2,
    parameters: { sheets: 'home' },
    args: {
        slots: {
            default: [
                { component: SiteHeader, props: { home: true } },
                { component: SampleHero },
                { component: SiteFooter, props: { home: true } },
            ],
        },
    },
    argTypes: {
        title: {
            description:
                'The page’s title. Defaults to the site’s (src/seo/site.ts).',
            control: 'text',
        },
        description: {
            description: 'What search engines show. Defaults to the site’s.',
            control: 'text',
        },
        slots: { table: { disable: true } },
    },
} satisfies Meta<V2Args>;

export default meta;

type Story = StoryObj<V2Args>;

export const Default: Story = {
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(page.getByRole('heading', { level: 1 }));
        await shows(page.getByRole('contentinfo'));
        // The layout marks the page as running scripts before the first paint.
        await expect(document.documentElement).toHaveClass('js');
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
