import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { ConnectionMark } from '@/components/connection-mark';
import type { ConnectionSlug } from '@/types';

const services: { slug: ConnectionSlug; label: string }[] = [
    { slug: 'google', label: 'Google' },
    { slug: 'github', label: 'GitHub' },
    { slug: 'discord', label: 'Discord' },
    { slug: 'wahoo', label: 'Wahoo' },
    { slug: 'ride_with_gps', label: 'Ride with GPS' },
    { slug: 'runsignup', label: 'RunSignup' },
    { slug: 'openstreetmap', label: 'OpenStreetMap' },
];

/**
 * The mark of each service a rider can connect, drawn inline so no other site sees a visitor. One colour, the
 * text colour, except Google's G. Wahoo, Ride with GPS, and RunSignup get a monogram. Always beside the service's
 * name: the mark is hidden from screen readers.
 */
const meta = {
    title: 'Components/ConnectionMark',
    component: ConnectionMark,
    args: { slug: 'google', size: 20 },
    argTypes: {
        slug: {
            control: 'select',
            options: services.map((service) => service.slug),
            description: 'The service whose mark to draw.',
        },
        size: {
            control: { type: 'range', min: 12, max: 64 },
            description: 'Width and height, in pixels.',
        },
        className: {
            control: 'text',
            description: 'Extra classes, such as a text colour.',
        },
    },
} satisfies Meta<typeof ConnectionMark>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Drawn at the size asked for, and hidden from screen readers and the Tab key. */
export const Default: Story = {
    play: async ({ canvasElement, args }) => {
        const marks = canvasElement.querySelectorAll('svg');

        await expect(marks.length).toBeGreaterThan(0);

        for (const mark of marks) {
            await expect(mark).toHaveAttribute('aria-hidden', 'true');
            await expect(mark).toHaveAttribute('focusable', 'false');
            await expect(mark).toHaveAttribute('width', String(args.size));
        }
    },
};

/** Each mark beside its service's name, which is what a screen reader reads. */
export const EveryService: Story = {
    render: (args) => (
        <ul className="grid gap-3">
            {services.map((service) => (
                <li key={service.slug} className="flex items-center gap-3">
                    <ConnectionMark {...args} slug={service.slug} />
                    <span className="text-sm">{service.label}</span>
                </li>
            ))}
        </ul>
    ),
    play: async (context) => {
        await expect(context.canvas.getAllByRole('listitem')).toHaveLength(
            services.length,
        );

        for (const service of services) {
            await expect(context.canvas.getByText(service.label)).toBeVisible();
        }

        await Default.play?.(context);
    },
};

export const Large: Story = {
    args: { slug: 'openstreetmap', size: 48 },
    play: Default.play,
};
