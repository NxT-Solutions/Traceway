import { expect, userEvent, waitFor, within } from 'storybook/test';
import SiteHeader from '../../components/SiteHeader.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

const sections = [
    'How it works',
    'Sports',
    'Multisport',
    'Devices',
    'Ask your AI',
    'FAQ',
];

/**
 * The bar at the top of every page: the mark and the name, the six sections of the home page, and
 * Open the planner. On the home page the section links scroll in place and the planner button
 * waits until the hero's own button has scrolled away. Below 900 pixels the sections move into a
 * menu: a tap opens it, Escape, the scrim or a link closes it, and the keyboard's focus follows.
 */
const meta = {
    title: 'Components/SiteHeader',
    component: SiteHeader,
    args: { home: false },
    argTypes: {
        home: {
            description:
                'On the home page: links scroll in place, and the brand goes back to the top.',
            control: 'boolean',
            table: { defaultValue: { summary: 'false' } },
        },
    },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta.args>;

/** On Support and the legal pages, each section link goes to the home page. */
export const InnerPage: Story = {
    name: 'Inner page',
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);
        const nav = within(page.getAllByRole('navigation', { name: 'Sections' })[0]);

        for (const name of sections) {
            await expect(nav.getByRole('link', { name })).toHaveAttribute(
                'href',
                expect.stringMatching(/^\/#/),
            );
        }
        await expect(page.getByRole('link', { name: 'Traceway home' })).toHaveAttribute(
            'href',
            '/',
        );
        await expect(
            page.getByRole('link', { name: /Open the planner/ }),
        ).toHaveAttribute('href', '/dashboard');
    },
};

/** On the home page the links stay on the page. */
export const HomePage: Story = {
    name: 'Home page',
    args: { home: true },
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);
        const nav = within(page.getAllByRole('navigation', { name: 'Sections' })[0]);

        await expect(nav.getByRole('link', { name: 'How it works' })).toHaveAttribute(
            'href',
            '#how',
        );
        await expect(
            page.getByRole('link', { name: 'Traceway, back to top' }),
        ).toHaveAttribute('href', '#top');
    },
};

/** On a phone: the menu opens with a tap, and Escape closes it and gives the focus back. */
export const PhoneMenu: Story = {
    name: 'Phone, menu',
    globals: { viewport: { value: 'phone', isRotated: false } },
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);
        const button = page.getByRole('button', { name: 'Open the menu' });
        const menu = canvasElement.querySelector<HTMLElement>('#menu');

        await expect(menu).not.toBeVisible();

        // The header's script has to have run before the button does anything.
        await waitFor(async () => {
            await userEvent.click(button);
            await expect(button).toHaveAttribute('aria-expanded', 'true');
        });
        // Open, not "visible": the menu fades in, and a frame the browser is not drawing yet (a
        // story opened straight from a link) holds the fade at its start.
        await expect(menu).not.toHaveAttribute('hidden');
        await expect(button).toHaveAccessibleName('Close the menu');
        for (const name of sections) {
            await expect(
                within(menu as HTMLElement).getByRole('link', { name }),
            ).toBeInTheDocument();
        }

        await userEvent.keyboard('{Escape}');
        await expect(menu).not.toBeVisible();
        await expect(button).toHaveAttribute('aria-expanded', 'false');
        await expect(button).toHaveFocus();
    },
};

/** On a phone, from the keyboard: Enter opens the menu with the focus on its first link. */
export const PhoneMenuKeyboard: Story = {
    name: 'Phone, menu from the keyboard',
    globals: { viewport: { value: 'phone', isRotated: false } },
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);
        const button = page.getByRole('button', { name: 'Open the menu' });

        await waitFor(async () => {
            button.focus();
            await userEvent.keyboard('{Enter}');
            await expect(button).toHaveAttribute('aria-expanded', 'true');
        });
        await expect(
            within(canvasElement.querySelector('#menu') as HTMLElement).getByRole(
                'link',
                { name: 'How it works' },
            ),
        ).toHaveFocus();

        // The scrim behind the menu closes it too.
        await userEvent.click(canvasElement.querySelector('#menu-scrim') as HTMLElement);
        await expect(button).toHaveAttribute('aria-expanded', 'false');
    },
};

export const Tablet: Story = {
    globals: { viewport: { value: 'tablet', isRotated: false } },
};
