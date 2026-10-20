import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { Breadcrumbs } from '@/components/breadcrumbs';

/**
 * The trail at the top of an app page, built from the page's breadcrumbs. Every step but the last is an Inertia
 * link; the last is the page itself. Nothing at all for an empty trail.
 */
const meta = {
    title: 'Components/Breadcrumbs',
    component: Breadcrumbs,
    args: {
        breadcrumbs: [
            { title: 'Saved routes', href: '/saved' },
            { title: 'Morning loop', href: '/saved/morning-loop' },
        ],
    },
    argTypes: {
        breadcrumbs: {
            control: 'object',
            description:
                'The steps, first to last, each a `title` and an `href`. The last is the page the rider is on.',
        },
    },
} satisfies Meta<typeof Breadcrumbs>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Every step but the last is a link; the last is the current page. Tab reaches the links in order. */
export const Default: Story = {
    play: async ({ canvas, args, userEvent }) => {
        const steps = args.breadcrumbs;
        const last = steps.at(-1)?.title ?? '';
        // The current page is not a link to follow: it carries aria-current and goes nowhere.
        const links = canvas
            .queryAllByRole('link')
            .filter((link) => !link.hasAttribute('aria-current'));

        await expect(
            canvas.getByRole('navigation', { name: /breadcrumb/i }),
        ).toBeVisible();
        await expect(links).toHaveLength(steps.length - 1);

        for (const [index, link] of links.entries()) {
            await expect(link).toHaveAccessibleName(steps[index]?.title ?? '');
            await expect(link).toHaveAttribute(
                'href',
                steps[index]?.href ?? '',
            );
        }

        await expect(canvas.getByText(last)).toHaveAttribute(
            'aria-current',
            'page',
        );
        await expect(canvas.getByText(last)).not.toHaveAttribute('href');

        if (links.length > 0) {
            await userEvent.tab();
            await expect(links[0]).toHaveFocus();
        }
    },
};

export const ThreeSteps: Story = {
    args: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Riders', href: '/admin/users' },
            { title: 'Sam Rivera', href: '/admin/users/1' },
        ],
    },
    play: Default.play,
};

export const OneStep: Story = {
    args: { breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }] },
    play: Default.play,
};

/** An empty trail draws nothing. */
export const Empty: Story = {
    args: { breadcrumbs: [] },
    play: async ({ canvas }) => {
        await expect(canvas.queryByRole('navigation')).not.toBeInTheDocument();
    },
};
