import '../../../../css/settings-scene.css';
import '../../../../css/admin-scene.css';
import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ReactNode } from 'react';
import { expect, waitFor, within } from 'storybook/test';
import DailyChart, { type DailyPoint } from '@/components/admin/daily-chart';
import { useAppearance } from '@/hooks/use-appearance';

/** The admin area around the chart, in Paper or Ink with the toolbar. */
function AdminScene({ children }: { children: ReactNode }) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings tw-admin"
            data-theme={resolvedAppearance}
            style={{ minHeight: 0, padding: 24 }}
        >
            <section className="panel" style={{ maxWidth: 720 }}>
                {children}
            </section>
        </div>
    );
}

/** Thirty days to 19 October 2026, busier at the weekend. */
const days: DailyPoint[] = Array.from({ length: 30 }, (_, index) => {
    const date = new Date(Date.UTC(2026, 8, 20 + index));
    const weekend = date.getUTCDay() === 0 || date.getUTCDay() === 6;

    return {
        date: date.toISOString().slice(0, 10),
        value: Math.round(
            (weekend ? 520 : 340) + 60 * Math.sin(index / 2) + index * 4,
        ),
    };
});

/** Active riders over the same days, a level rather than a count. */
const active: DailyPoint[] = days.map((point, index) => ({
    date: point.date,
    value: 610 + index * 9 + Math.round(25 * Math.sin(index / 3)),
}));

/** One day by the hour. */
const hours: DailyPoint[] = Array.from({ length: 24 }, (_, hour) => ({
    date: new Date(Date.UTC(2026, 9, 19, hour)).toISOString(),
    value: Math.round(
        Math.max(0, 40 * Math.sin(((hour - 5) / 24) * Math.PI * 2) + 30),
    ),
}));

/**
 * One measure over days: bars for counts, a line for a level such as active riders. One series, so the title
 * names it and there is no legend. A table beside it, for screen readers, carries the same numbers.
 */
const meta = {
    title: 'Components/Admin/DailyChart',
    component: DailyChart,
    args: { data: days, unit: 'routes', label: 'Routes planned each day' },
    argTypes: {
        data: {
            control: 'object',
            description:
                'One point per day (a date such as 2026-10-19) or per hour (a moment, with `hourly`), oldest first.',
        },
        unit: {
            control: 'text',
            description:
                'What is counted, in the plural: routes, riders, requests. Shown in the tip and the table.',
        },
        kind: {
            control: 'inline-radio',
            options: ['bar', 'line'],
            description:
                'Bars for counts; a line for a level such as active riders.',
        },
        label: {
            control: 'text',
            description:
                'Names the one series. The caption of the chart and of the table screen readers read.',
        },
        hourly: {
            control: 'boolean',
            description:
                'Reads the points as hours of one day instead of days.',
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
} satisfies Meta<typeof DailyChart>;

export default meta;

type Story = StoryObj<typeof meta>;

/**
 * The chart is named by its caption and stays out of the tab order; a table with the same numbers is there for
 * screen readers. Pointing at a bar shows its day and its count in a tip.
 */
export const Bars: Story = {
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        const figure = canvas.getByRole('figure');
        await expect(figure.querySelector('figcaption')).toHaveTextContent(
            args.label,
        );
        const table = within(figure).getByRole('table', { name: args.label });

        // A header row, then one row per point.
        await expect(within(table).getAllByRole('row')).toHaveLength(
            args.data.length + 1,
        );
        await expect(
            within(table).getByRole('columnheader', { name: args.unit }),
        ).toBeInTheDocument();

        const surface = await waitFor(() => {
            const found = canvasElement.querySelector('.recharts-surface');
            if (found === null) {
                throw new Error('The chart has not drawn yet.');
            }
            return found;
        });
        const box = surface.getBoundingClientRect();

        await userEvent.pointer({
            target: surface,
            coords: {
                clientX: box.right - 30,
                clientY: box.top + box.height / 2,
            },
        });
        await waitFor(() =>
            expect(canvasElement.querySelector('.tip')).toHaveTextContent(
                args.unit,
            ),
        );
    },
};

export const Line: Story = {
    args: {
        data: active,
        kind: 'line',
        unit: 'riders',
        label: 'Riders active in the last 7 days',
    },
    play: Bars.play,
};

export const Hourly: Story = {
    args: {
        data: hours,
        hourly: true,
        unit: 'requests',
        label: 'Requests each hour on 19 October',
    },
    play: Bars.play,
};
