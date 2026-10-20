import { expect, within } from 'storybook/test';
import LegalPage from '../../pages/legal.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * Who makes and runs Traceway (src/legal.ts, notice). It renders in Base, with the header and the footer.
 */
const meta = {
    title: 'Pages/Legal',
    component: LegalPage,
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        await shows(within(canvasElement).getByRole('heading', { level: 1, name: 'Legal notice' }));
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
