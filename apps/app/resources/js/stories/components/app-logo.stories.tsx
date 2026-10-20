import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import AppLogo from '@/components/app-logo';

/**
 * The mark and the word Traceway, as the app header and the sidebar show it. The text takes the foreground colour,
 * so it reads on Paper and on Ink.
 */
const meta = {
    title: 'Components/AppLogo',
    component: AppLogo,
} satisfies Meta<typeof AppLogo>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The word is read once: the mark beside it is hidden from screen readers. */
export const Default: Story = {
    play: async ({ canvas }) => {
        await expect(canvas.getByText('Traceway')).toBeVisible();
        await expect(canvas.queryByRole('img')).not.toBeInTheDocument();
    },
};
