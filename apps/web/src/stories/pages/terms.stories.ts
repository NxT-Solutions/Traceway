import { expect, within } from 'storybook/test';
import TermsPage from '../../pages/terms.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * The terms of use (src/legal.ts, terms). Its Markdown twin is /terms.md. It renders in Base, with the header and the footer.
 */
const meta = {
    title: 'Pages/Terms',
    component: TermsPage,
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        await shows(within(canvasElement).getByRole('heading', { level: 1, name: 'Terms of use' }));
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
