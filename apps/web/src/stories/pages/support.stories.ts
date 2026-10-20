import { expect, within } from 'storybook/test';
import SupportPage from '../../pages/support.astro';
import { support } from '../../support';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * Why Traceway is free, and how to help pay for the server: PayPal, GitHub Sponsors, or any amount to paypal.me. Donating unlocks nothing. It renders in Base, with the header and the footer.
 */
const meta = {
    title: 'Pages/Support',
    component: SupportPage,
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(page.getByRole('heading', { level: 1, name: 'Free today. Free for good.' }));
        await expect(
            page.getByRole('link', { name: 'Donate with PayPal' }),
        ).toHaveAttribute('href', support.paypal);
        await expect(
            page.getByRole('link', { name: 'Sponsor on GitHub' }),
        ).toHaveAttribute('href', support.github);
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
