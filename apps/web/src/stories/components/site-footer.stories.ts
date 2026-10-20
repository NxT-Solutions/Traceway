import { expect, within } from 'storybook/test';
import SiteFooter from '../../components/SiteFooter.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * The foot of every page: the home page's sections, the account links (with Storybook), the legal pages, and Cookie
 * choice, which asks the cookie question again (see Consent). On the home page the section links
 * scroll in place.
 */
const meta = {
    title: 'Components/SiteFooter',
    component: SiteFooter,
    args: { home: false },
    argTypes: {
        home: {
            description:
                'On the home page: section links scroll in place, and the brand goes back to the top.',
            control: 'boolean',
            table: { defaultValue: { summary: 'false' } },
        },
    },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta.args>;

/** On Support and the legal pages. */
export const InnerPage: Story = {
    name: 'Inner page',
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await expect(page.getByRole('link', { name: 'How it works' })).toHaveAttribute(
            'href',
            '/#how',
        );
        for (const [name, href] of [
            ['Privacy policy', '/privacy'],
            ['Terms of use', '/terms'],
            ['Cookies', '/cookies'],
            ['Legal notice', '/legal'],
            ['Support Traceway', '/support'],
            ['Storybook', '/storybook'],
        ]) {
            await expect(page.getByRole('link', { name })).toHaveAttribute('href', href);
        }
        await shows(page.getByRole('button', { name: 'Cookie choice' }));
    },
};

/** On the home page. */
export const HomePage: Story = {
    name: 'Home page',
    args: { home: true },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('link', { name: 'How it works' }),
        ).toHaveAttribute('href', '#how');
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
