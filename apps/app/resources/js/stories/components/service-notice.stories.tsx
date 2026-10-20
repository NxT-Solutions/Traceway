import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { ServiceNotice } from '@/components/service-notice';
import { status } from '@/routes';
import { PlannerScene } from '../support/planner-scene';

/**
 * A line above the map when route drawing, place search, or the maps are down or slow, or when a notice is
 * posted. Closing it hides that notice for this visit; a new one shows again.
 */
const meta = {
    title: 'Components/ServiceNotice',
    component: ServiceNotice,
    args: {
        notice: {
            title: 'Route drawing is slow right now.',
            body: 'A loop can take up to a minute. Your plan is safe.',
        },
    },
    argTypes: {
        notice: {
            control: 'object',
            description:
                'The notice: a `title` in bold and an optional `body`. null shows nothing.',
        },
    },
    parameters: { layout: 'fullscreen' },
    beforeEach: () => {
        try {
            window.sessionStorage.removeItem(
                'traceway:service-notice-dismissed',
            );
        } catch {
            // Private windows may refuse storage.
        }
    },
    decorators: [
        (Story) => (
            <PlannerScene style={{ padding: 0 }}>
                <Story />
            </PlannerScene>
        ),
    ],
} satisfies Meta<typeof ServiceNotice>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Read out as a status, with a link to the status page. */
export const Slow: Story = {
    play: async ({ canvas, args }) => {
        const note = canvas.getByRole('status');

        await expect(note).toHaveTextContent(args.notice?.title ?? '');
        await expect(note).toHaveTextContent(args.notice?.body ?? '');
        await expect(
            canvas.getByRole('link', { name: 'See the status page' }),
        ).toHaveAttribute('href', status.url());
    },
};

/** Closing it, from the keyboard, hides it for this visit. */
export const Closed: Story = {
    play: async ({ canvas, userEvent }) => {
        const close = canvas.getByRole('button', { name: 'Close this notice' });

        close.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.queryByRole('status')).not.toBeInTheDocument();
        await expect(
            window.sessionStorage.getItem('traceway:service-notice-dismissed'),
        ).toBe('Route drawing is slow right now.');
    },
};

export const TitleOnly: Story = {
    args: {
        notice: {
            title: 'Place search is down. Tap the map to set a start.',
            body: null,
        },
    },
    play: async ({ canvas, args }) => {
        await expect(canvas.getByRole('status')).toHaveTextContent(
            `${args.notice?.title} See the status page`,
        );
    },
};

/** No notice: nothing renders. */
export const None: Story = {
    args: { notice: null },
    play: async ({ canvas }) => {
        await expect(canvas.queryByRole('status')).not.toBeInTheDocument();
    },
};
