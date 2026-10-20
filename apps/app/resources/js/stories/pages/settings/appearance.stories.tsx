import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, waitFor } from 'storybook/test';
import Appearance from '@/pages/settings/appearance';
import { InertiaPage, sharedProps } from '../../support/inertia-page';

/**
 * Settings, Appearance (`/settings/appearance`): Light, Dark, or System. The page takes no props; the choice lives
 * in the browser, so the Appearance toolbar above picks the one that shows pressed.
 *
 * `SwitchesTheme` needs no server: the choice is saved in the browser (local storage and a cookie), and the page
 * restyles at once. The next story puts the toolbar's appearance back.
 */
const meta = {
    title: 'Pages/Settings/Appearance',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    render: () => (
        <InertiaPage
            name="settings/appearance"
            component={Appearance}
            props={{}}
            shared={sharedProps()}
            url="/settings/appearance"
        />
    ),
};

/** Sam picks Dark, then Light: the page, the `dark` class and the saved choice follow each time. */
export const SwitchesTheme: Story = {
    render: Default.render,
    play: async ({ canvas, canvasElement, userEvent, step }) => {
        const settings = () =>
            canvasElement.querySelector('.tw-settings') as HTMLElement;

        await step('Pick Dark', async () => {
            await userEvent.click(canvas.getByRole('button', { name: 'Dark' }));
            await expect(
                canvas.getByRole('button', { name: 'Dark' }),
            ).toHaveAttribute('aria-pressed', 'true');
            await waitFor(() =>
                expect(settings()).toHaveAttribute('data-theme', 'dark'),
            );
            await expect(document.documentElement).toHaveClass('dark');
            await expect(localStorage.getItem('appearance')).toBe('dark');
        });

        await step('Pick Light', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Light' }),
            );
            await expect(
                canvas.getByRole('button', { name: 'Light' }),
            ).toHaveAttribute('aria-pressed', 'true');
            await waitFor(() =>
                expect(settings()).toHaveAttribute('data-theme', 'light'),
            );
            await expect(document.documentElement).not.toHaveClass('dark');
            await expect(localStorage.getItem('appearance')).toBe('light');
        });
    },
};
