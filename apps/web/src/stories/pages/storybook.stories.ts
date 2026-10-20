import { expect, within } from 'storybook/test';
import StorybookPage from '../../pages/storybook.astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * The way into Traceway's two Storybooks, at traceway.online/storybook: the planner's at /storybook/app/ and this
 * site's at /storybook/web/. It renders in Base, with the header and the footer, and is kept out of the sitemap.
 */
const meta = {
    title: 'Pages/Storybook',
    component: StorybookPage,
} satisfies Meta;

export default meta;

type Story = StoryObj;

export const Desktop: Story = {
    play: async ({ canvasElement }) => {
        const page = within(canvasElement);

        await shows(
            page.getByRole('heading', {
                level: 1,
                name: 'Every part of Traceway, one by one.',
            }),
        );
        await expect(
            page.getByRole('link', { name: 'Planner Storybook' }),
        ).toHaveAttribute('href', '/storybook/app/');
        await expect(
            page.getByRole('link', { name: 'Website Storybook' }),
        ).toHaveAttribute('href', '/storybook/web/');
    },
};

export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
