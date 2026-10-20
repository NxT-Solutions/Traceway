import { expect, within } from 'storybook/test';
import NotFoundPage from '../../pages/404.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/** What the site shows for an address it does not have: the error page for 404. */
const meta = {
    title: 'Pages/404',
    component: NotFoundPage,
    parameters: { sheets: 'errors', bodyClass: () => 'is-run' },
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        await shows(within(canvasElement).getByRole('heading', { level: 1, name: 'Off the map' }));
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
