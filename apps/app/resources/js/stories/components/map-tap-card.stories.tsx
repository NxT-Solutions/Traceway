import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import { MapTapCard } from '@/components/map-tap-card';
import { PlannerScene } from '../support/planner-scene';

/**
 * A tap on the map asks before it moves a pin. A ring marks the spot, and a small card names the place and offers
 * what the tap can do there, so panning or a stray tap never moves anything. Escape, the close button, or moving
 * the map puts it away. The card sits above the ring, or below it near the top edge, always inside the map.
 */
const meta = {
    title: 'Components/MapTapCard',
    component: MapTapCard,
    args: {
        x: 320,
        y: 220,
        name: 'Boerenkrijgsingel, Westerlo',
        note: null,
        actions: [
            { label: 'Start here', onSelect: fn() },
            { label: 'Pass through here', onSelect: fn() },
        ],
        onClose: fn(),
    },
    argTypes: {
        x: {
            control: { type: 'range', min: 0, max: 640 },
            description: "Where the tap landed, in pixels from the map's left.",
        },
        y: {
            control: { type: 'range', min: 0, max: 360 },
            description: "Where the tap landed, in pixels from the map's top.",
        },
        name: {
            control: 'text',
            description:
                'The place around the tap, or null while it is being named.',
        },
        note: {
            control: 'text',
            description:
                'Said while the spot has no road yet, above any actions.',
        },
        actions: {
            control: 'object',
            description:
                'What the tap can do there, first one filled. None while the spot has no road.',
        },
        onClose: {
            action: 'closed',
            description: 'Escape or the close button.',
        },
    },
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <PlannerScene>
                <div
                    style={{
                        position: 'relative',
                        height: 360,
                        maxWidth: 640,
                        borderRadius: 16,
                        overflow: 'hidden',
                        background: 'var(--m-base)',
                    }}
                >
                    <Story />
                </div>
            </PlannerScene>
        ),
    ],
} satisfies Meta<typeof MapTapCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Focus lands on the first action; choosing one asks for it, and Escape or Close puts the card away. */
export const Default: Story = {
    play: async ({ canvas, args, userEvent, step }) => {
        const card = canvas.getByRole('dialog', {
            name: 'Boerenkrijgsingel, Westerlo',
        });
        const start = canvas.getByRole('button', { name: 'Start here' });

        await expect(card).toBeVisible();
        await expect(start).toHaveFocus();

        await step('Start here', async () => {
            await userEvent.click(start);
            await expect(args.actions[0]?.onSelect).toHaveBeenCalledOnce();
        });

        await step('Pass through here, from the keyboard', async () => {
            await userEvent.tab();
            await expect(
                canvas.getByRole('button', { name: 'Pass through here' }),
            ).toHaveFocus();
            await userEvent.keyboard('{Enter}');
            await expect(args.actions[1]?.onSelect).toHaveBeenCalledOnce();
        });

        await step('Escape and Close put it away', async () => {
            await userEvent.keyboard('{Escape}');
            await expect(args.onClose).toHaveBeenCalledTimes(1);
            await userEvent.click(
                canvas.getByRole('button', { name: 'Close' }),
            );
            await expect(args.onClose).toHaveBeenCalledTimes(2);
        });
    },
};

/** While the place is being named. */
export const Naming: Story = {
    args: { name: null },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('dialog', { name: 'This spot' }),
        ).toBeVisible();
    },
};

/** No road near the tap yet: a note, and no actions. */
export const NoRoad: Story = {
    args: {
        name: 'De Merode',
        note: 'No road or path here. Tap closer to one.',
        actions: [],
    },
    play: async ({ canvas, args }) => {
        await expect(canvas.getByRole('status')).toHaveTextContent(
            args.note ?? '',
        );
        await expect(
            canvas.queryByRole('button', { name: 'Start here' }),
        ).not.toBeInTheDocument();
        await expect(
            canvas.getByRole('button', { name: 'Close' }),
        ).toBeVisible();
    },
};

/** Near the top edge, the card drops below the ring. */
export const NearTheTop: Story = {
    args: { y: 40 },
    play: async ({ canvas, canvasElement, args }) => {
        const card = canvas.getByRole('dialog');
        const map = canvasElement.querySelector('.tap-card')?.parentElement;
        const top =
            card.getBoundingClientRect().top -
            (map?.getBoundingClientRect().top ?? 0);

        await expect(top).toBeGreaterThan(args.y);
    },
};

/** Near the right edge, the card stays inside the map. */
export const NearTheEdge: Story = {
    args: { x: 620, y: 260 },
    play: async ({ canvas, canvasElement }) => {
        const card = canvas.getByRole('dialog').getBoundingClientRect();
        const map = canvasElement
            .querySelector('.tap-card')
            ?.parentElement?.getBoundingClientRect();

        await expect(card.right).toBeLessThanOrEqual(map?.right ?? 0);
    },
};
