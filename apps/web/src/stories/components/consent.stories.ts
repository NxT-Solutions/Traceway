import { expect, userEvent, waitFor, within } from 'storybook/test';
import Consent from '../../components/Consent.astro';
import SiteFooter from '../../components/SiteFooter.astro';
import Stack from '../support/Stack.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

const key = 'traceway.consent';

const stored = (): string | null => localStorage.getItem(key);

/** The cookie question, once its script has run and shown it. */
const question = async (canvasElement: HTMLElement): Promise<HTMLElement> => {
    const section = canvasElement.querySelector<HTMLElement>('[data-consent]');

    await expect(section).not.toBeNull();
    await waitFor(() => expect(section).toBeVisible());

    return section as HTMLElement;
};

/**
 * The question every page asks before the site counts a visit. Nothing from Cloudflare loads until
 * the visitor says yes, and only on traceway.online, so never here. The answer is kept in this
 * browser for a year as `yes:<when>` or `no:<when>` under `traceway.consent`. Escape puts the
 * question away for this page without an answer. Cookie choice, in the footer, asks again.
 */
const meta = {
    title: 'Components/Consent',
    component: Consent,
    parameters: { consent: 'ask' },
    // Its own size on a phone: the banner is fixed to the bottom of the screen.
    globals: { viewport: { value: 'phone', isRotated: false } },
} satisfies Meta;

export default meta;

type Story = StoryObj;

/** A first visit: the question is open. Yes keeps the answer and closes it. */
export const AcceptCounting: Story = {
    name: 'First visit, yes',
    play: async ({ canvasElement }) => {
        const banner = await question(canvasElement);

        await userEvent.click(
            within(banner).getByRole('button', { name: 'Yes, that’s fine' }),
        );

        await expect(banner).not.toBeVisible();
        await expect(stored()).toMatch(/^yes:\d+$/);
        // The counter loads only on the live site.
        await expect(
            document.querySelector('script[src*="cloudflareinsights"]'),
        ).toBeNull();
    },
};

/** A first visit: No keeps that answer and closes the question. */
export const DeclineCounting: Story = {
    name: 'First visit, no',
    play: async ({ canvasElement }) => {
        const banner = await question(canvasElement);

        await userEvent.click(
            within(banner).getByRole('button', { name: 'No, thanks' }),
        );

        await expect(banner).not.toBeVisible();
        await expect(stored()).toMatch(/^no:\d+$/);
    },
};

/** Escape puts the question away for this page, and nothing is kept. */
export const DismissWithEscape: Story = {
    name: 'First visit, Escape',
    play: async ({ canvasElement }) => {
        const banner = await question(canvasElement);

        within(banner).getByRole('button', { name: 'No, thanks' }).focus();
        await userEvent.keyboard('{Escape}');

        await expect(banner).not.toBeVisible();
        await expect(stored()).toBeNull();
    },
};

/**
 * Answered before: the question stays shut. Cookie choice in the footer opens it with the focus on
 * its title, and an answer hands the focus back.
 */
export const AskAgain: Story = {
    name: 'Answered, asked again',
    parameters: { consent: 'answered' },
    decorators: [
        (Story) => ({
            component: Stack,
            slots: { default: [Story(), { component: SiteFooter }] },
        }),
    ],
    play: async ({ canvasElement }) => {
        const banner = canvasElement.querySelector<HTMLElement>('[data-consent]');
        const page = within(canvasElement);

        await expect(banner).not.toBeVisible();

        const choice = page.getByRole('button', { name: 'Cookie choice' });

        // The footer's button only works once the question's script has run.
        await waitFor(async () => {
            await userEvent.click(choice);
            await expect(banner).toBeVisible();
        });
        await expect(
            page.getByRole('heading', { name: 'Can we count your visit?' }),
        ).toHaveFocus();

        await userEvent.click(page.getByRole('button', { name: 'Yes, that’s fine' }));

        await expect(banner).not.toBeVisible();
        await expect(stored()).toMatch(/^yes:\d+$/);
        await expect(choice).toHaveFocus();
    },
};
