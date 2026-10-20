import { expect, within } from 'storybook/test';
import CookiesPage from '../../pages/cookies.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * What the site and the planner keep in the browser, and the cookie question (src/legal.ts, cookies). It renders in Base, with the header and the footer.
 */
const meta = {
    title: 'Pages/Cookies',
    component: CookiesPage,
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        await shows(within(canvasElement).getByRole('heading', { level: 1, name: 'Cookies and storage' }));
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
