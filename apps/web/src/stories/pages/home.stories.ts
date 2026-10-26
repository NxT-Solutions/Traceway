import { expect, userEvent, waitFor, within } from 'storybook/test';
import Home from '../../pages/index.astro';
import { onDocsPage, shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * The site's one page (src/pages/index.astro, src/home/body.html): the loop drawn as you scroll,
 * the sports, multisport, the devices, asking an AI, and the questions. The scroll scene loads
 * GSAP after the first scroll, tap or key, as on the site. Links that leave the page point at the
 * live addresses, so they lead nowhere here.
 */
const meta = {
    title: 'Pages/Home',
    component: Home,
    parameters: { sheets: 'home' },
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(page.getByRole('heading', { level: 1, name: /Name the distance/ }));
        // The town a visitor types goes to the planner, which opens there. Left empty, the
        // button just opens the planner.
        const town = page.getByRole('textbox', { name: 'Where do you start?' });

        await expect(town).toHaveAttribute('name', 'near');
        await expect(town.closest('form')).toHaveAttribute('action', '/dashboard');
        await expect(town.closest('form')).toHaveAttribute('method', 'get');
        // A first-time visitor learns, by the button, that they will need a free account.
        await expect(canvasElement.querySelector('.hero-note')).toHaveTextContent(
            /Free account, no card/,
        );

        // The devices section sends a Garmin rider to the app in the Connect IQ store.
        await expect(
            page.getByRole('link', { name: 'Get the Traceway app for Garmin' }),
        ).toHaveAttribute('href', 'https://apps.garmin.com/apps/a0c32e89-323f-4522-9ee8-c6af6cce14c3');

        // Every section link in the header lands on a section of this page.
        const sections = within(
            page.getAllByRole('navigation', { name: 'Sections' })[0],
        ).getAllByRole('link');

        for (const link of sections) {
            const hash = link.getAttribute('href') ?? '';

            await expect(hash).toMatch(/^#/);
            await expect(canvasElement.querySelector(hash)).not.toBeNull();
        }

        // A question opens in place, and closes again.
        const faq = canvasElement.querySelector<HTMLElement>('#faq');
        const first = faq?.querySelector('details');
        const summary = first?.querySelector('summary');

        await expect(summary).toBeTruthy();
        await userEvent.click(summary as HTMLElement);
        await expect(first).toHaveAttribute('open');
        await userEvent.click(summary as HTMLElement);
        await expect(first).not.toHaveAttribute('open');
    },
};

export const Tablet: Story = {
    globals: { viewport: { value: 'tablet', isRotated: false } },
};

/** On a phone the sections move into the menu. */
export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
    play: async ({ canvasElement }) => {
        if (onDocsPage()) {
            return;
        }

        const page = within(canvasElement);
        const button = page.getByRole('button', { name: 'Open the menu' });

        // The header's script has to have run before the button does anything.
        await waitFor(async () => {
            await userEvent.click(button);
            await expect(button).toHaveAttribute('aria-expanded', 'true');
        });
        // Open rather than visible: the menu fades in (see SiteHeader, Phone, menu).
        await expect(canvasElement.querySelector('#menu')).not.toHaveAttribute('hidden');

        // A section link closes the menu. The story stays where it is: the link's own jump to
        // its section is the browser's, and in a test frame it would leave the page.
        canvasElement.addEventListener('click', (event) => event.preventDefault(), {
            once: true,
        });
        await userEvent.click(
            within(canvasElement.querySelector('#menu') as HTMLElement).getByRole(
                'link',
                { name: /FAQ/ },
            ),
        );
        await expect(button).toHaveAttribute('aria-expanded', 'false');
    },
};

/** A first visit: the cookie question waits at the bottom until it is answered. */
export const FirstVisit: Story = {
    name: 'First visit',
    parameters: { consent: 'ask' },
    play: async ({ canvasElement }) => {
        const question = canvasElement.querySelector('[data-consent]');

        await waitFor(() => expect(question).toBeVisible());
        await userEvent.click(
            within(question as HTMLElement).getByRole('button', { name: 'No, thanks' }),
        );
        await expect(question).not.toBeVisible();
    },
};
