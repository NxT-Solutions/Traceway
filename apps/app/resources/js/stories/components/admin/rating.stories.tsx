import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect } from 'storybook/test';
import Rating from '@/components/admin/rating';
import { useAppearance } from '@/hooks/use-appearance';

/** The admin area around the rating, in Paper or Ink with the toolbar. */
function AdminScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            {children}
        </div>
    );
}

/**
 * A rating in the status colours, always with its icon and its word, so colour is never the only sign. A number
 * can sit before the word.
 */
const meta = {
    title: 'Components/Admin/Rating',
    component: Rating,
    args: { level: 'good', label: 'Good' },
    argTypes: {
        level: {
            control: 'inline-radio',
            options: ['good', 'needs_work', 'poor'],
            description:
                'Sets the colour and the icon: a tick, an exclamation mark, or a cross.',
        },
        label: {
            control: 'text',
            description:
                'The word that says the rating, such as Good or Stopped. Always shown.',
        },
        children: {
            control: 'text',
            description: 'A number before the word, such as 1.6 s.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <AdminScene>
                <Story />
            </AdminScene>
        ),
    ],
} satisfies Meta<typeof Rating>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The word is there to read; the icon beside it is decoration for screen readers. */
export const Good: Story = {
    play: async ({ args, canvas, canvasElement }) => {
        await expect(canvas.getByText(args.label)).toBeVisible();
        await expect(
            canvasElement.querySelector(`.rating.${args.level} svg`),
        ).toHaveAttribute('aria-hidden', 'true');
    },
};

export const Levels: Story = {
    render: () => (
        <div className="flex flex-col gap-3">
            <Rating level="good" label="Good">
                1.6 s
            </Rating>
            <Rating level="needs_work" label="Needs work">
                3.1 s
            </Rating>
            <Rating level="poor" label="Poor">
                5.4 s
            </Rating>
        </div>
    ),
    play: async ({ canvas, canvasElement }) => {
        // Each level has its own icon and word, not only its own colour.
        const ratings = [...canvasElement.querySelectorAll('.rating')];

        await expect(ratings.map((rating) => rating.textContent)).toEqual([
            '1.6 sGood',
            '3.1 sNeeds work',
            '5.4 sPoor',
        ]);
        await expect(
            new Set(
                ratings.map((rating) =>
                    rating.querySelector('svg')?.getAttribute('class'),
                ),
            ).size,
        ).toBe(3);
        await expect(canvas.getByText('Needs work')).toBeVisible();
    },
};

export const BackupStopped: Story = {
    args: { level: 'poor', label: 'Stopped' },
    play: Good.play,
};
