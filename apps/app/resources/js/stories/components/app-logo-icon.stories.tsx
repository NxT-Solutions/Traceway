import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * The Traceway mark alone: a route that turns, and the chartreuse marker where it ends. The line takes the text
 * colour; the marker stays chartreuse. Alone it is an image named Traceway; beside the word, pass
 * `aria-hidden="true"` so the name is not read twice.
 */
const meta = {
    title: 'Components/AppLogoIcon',
    component: AppLogoIcon,
    args: { className: 'size-10' },
    argTypes: {
        className: {
            control: 'select',
            options: ['size-5', 'size-7', 'size-10', 'size-16'],
            description: 'The size, as a Tailwind size class.',
        },
        'aria-hidden': {
            control: 'boolean',
            description:
                'Hide it from screen readers when the word Traceway is beside it.',
        },
    },
} satisfies Meta<typeof AppLogoIcon>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A screen reader reads it as the image "Traceway". */
export const Default: Story = {
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('img', { name: 'Traceway' }),
        ).toBeVisible();
    },
};

export const Sizes: Story = {
    render: (args) => (
        <div className="flex items-end gap-4">
            <AppLogoIcon {...args} className="size-5" />
            <AppLogoIcon {...args} className="size-7" />
            <AppLogoIcon {...args} className="size-10" />
            <AppLogoIcon {...args} className="size-16" />
        </div>
    ),
    play: async ({ canvas }) => {
        const marks = canvas.getAllByRole('img', { name: 'Traceway' });

        const widths = marks.map((mark) => mark.getBoundingClientRect().width);

        await expect(marks).toHaveLength(4);
        await expect(widths[0] ?? 0).toBeLessThan(widths[3] ?? 0);
    },
};

/** On an ink tile, as the app icon. */
export const OnInk: Story = {
    render: (args) => (
        <div className="inline-flex rounded-2xl bg-[oklch(0.22_0.03_200)] p-4 text-[oklch(0.97_0.012_95)]">
            <AppLogoIcon {...args} />
        </div>
    ),
    play: Default.play,
};
