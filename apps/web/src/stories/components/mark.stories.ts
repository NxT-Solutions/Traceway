import { expect } from 'storybook/test';
import Mark from '../../components/Mark.astro';
import type { Meta, StoryObj } from '../support/types';

/**
 * Traceway's mark: a route that turns back, and the dot where it ends. It takes the text colour
 * around it. The header uses it at 28 pixels, the footer at 22.
 */
const meta = {
    title: 'Components/Mark',
    component: Mark,
    parameters: { layout: 'centered' },
    args: { size: 28 },
    argTypes: {
        size: {
            description: 'Width and height, in pixels.',
            control: { type: 'range', min: 16, max: 160, step: 2 },
            table: { defaultValue: { summary: '28' } },
        },
    },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta.args>;

/** In the header, beside the name. */
export const Header: Story = {
    play: async ({ canvasElement }) => {
        const mark = canvasElement.querySelector('svg');

        await expect(mark).toHaveAttribute('width', '28');
        await expect(mark).toHaveAttribute('aria-hidden', 'true');
    },
};

/** In the footer. */
export const Footer: Story = { args: { size: 22 } };

export const Large: Story = { args: { size: 48 } };

export const Hero: Story = { args: { size: 96 } };
