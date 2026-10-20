import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';

/**
 * Where a page sits: the trail back to the pages above it, and the current page last, not a link.
 */
const meta = {
    title: 'UI/Breadcrumb',
    component: Breadcrumb,
    argTypes: {
        'aria-label': {
            control: 'text',
            description: 'Name of the navigation landmark.',
            table: { defaultValue: { summary: 'breadcrumb' } },
        },
    },
    render: (args) => (
        <Breadcrumb {...args}>
            <BreadcrumbList>
                <BreadcrumbItem>
                    <BreadcrumbLink href="#">Saved routes</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbPage>Morning loop</BreadcrumbPage>
                </BreadcrumbItem>
            </BreadcrumbList>
        </Breadcrumb>
    ),
} satisfies Meta<typeof Breadcrumb>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvas, userEvent }) => {
        await expect(
            canvas.getByRole('navigation', { name: 'breadcrumb' }),
        ).toBeInTheDocument();

        // The trail back is a real link, reached with Tab; the current page is not.
        await userEvent.tab();
        await expect(
            canvas.getByRole('link', { name: 'Saved routes' }),
        ).toHaveFocus();
        await expect(canvas.getByText('Morning loop')).toHaveAttribute(
            'aria-current',
            'page',
        );
    },
};

/** A long trail folds its middle into an ellipsis. */
export const Collapsed: Story = {
    render: (args) => (
        <Breadcrumb {...args}>
            <BreadcrumbList>
                <BreadcrumbItem>
                    <BreadcrumbLink href="#">Admin</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbEllipsis />
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbLink href="#">Riders</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbPage>Sam Rivera</BreadcrumbPage>
                </BreadcrumbItem>
            </BreadcrumbList>
        </Breadcrumb>
    ),
    play: async ({ canvas, userEvent }) => {
        // Tab skips the ellipsis: Admin, then Riders.
        await userEvent.tab();
        await expect(canvas.getByRole('link', { name: 'Admin' })).toHaveFocus();
        await userEvent.tab();
        await expect(
            canvas.getByRole('link', { name: 'Riders' }),
        ).toHaveFocus();
        await expect(canvas.getByText('Sam Rivera')).toHaveAttribute(
            'aria-current',
            'page',
        );
    },
};
