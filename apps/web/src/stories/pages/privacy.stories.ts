import { expect, within } from 'storybook/test';
import PrivacyPage from '../../pages/privacy.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * The privacy policy (src/legal.ts, privacy). Its Markdown twin is /privacy.md. It renders in Base, with the header and the footer.
 */
const meta = {
    title: 'Pages/Privacy',
    component: PrivacyPage,
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        await shows(within(canvasElement).getByRole('heading', { level: 1, name: 'Privacy policy' }));
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
