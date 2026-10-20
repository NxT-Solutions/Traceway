import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import AppearanceToggleTab from '@/components/appearance-tabs';

/**
 * Light, Dark, or System, on the Appearance settings page. It changes the same appearance as the toolbar above,
 * so choosing one here turns the whole story with it. The chosen one is pressed.
 */
const meta = {
    title: 'Components/AppearanceTabs',
    component: AppearanceToggleTab,
    argTypes: {
        className: {
            control: 'text',
            description: 'Extra classes on the group, such as a margin.',
        },
    },
} satisfies Meta<typeof AppearanceToggleTab>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Choosing the other appearance turns the page; the keyboard turns it back. */
export const Default: Story = {
    play: async ({ canvas, userEvent, step }) => {
        const root = document.documentElement;
        const startedDark = root.classList.contains('dark');
        const current = canvas.getByRole('button', {
            name: startedDark ? 'Dark' : 'Light',
        });
        const other = canvas.getByRole('button', {
            name: startedDark ? 'Light' : 'Dark',
        });

        await expect(
            canvas.getByRole('group', { name: 'Appearance' }),
        ).toBeVisible();
        await expect(current).toHaveAttribute('aria-pressed', 'true');

        await step('Choose the other appearance', async () => {
            await userEvent.click(other);
            await expect(other).toHaveAttribute('aria-pressed', 'true');
            await expect(current).toHaveAttribute('aria-pressed', 'false');
            await expect(root.classList.contains('dark')).toBe(!startedDark);
        });

        await step('Back with the keyboard', async () => {
            current.focus();
            await userEvent.keyboard('{Enter}');
            await expect(current).toHaveAttribute('aria-pressed', 'true');
            await expect(root.classList.contains('dark')).toBe(startedDark);
        });
    },
};
