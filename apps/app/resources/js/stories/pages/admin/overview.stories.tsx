import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import Overview from '@/pages/admin/overview';
import { overview } from '../../support/admin-pages';
import { adminAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';

type OverviewProps = ComponentProps<typeof Overview>;

/**
 * The admin home at `/admin`, with the props AdminOverviewController renders: who signed up, who is active, what
 * riders saved and used, and which apps and assistants they connected. All the numbers are examples.
 */
const meta = {
    title: 'Pages/Admin/Overview',
    component: Overview,
    parameters: { layout: 'fullscreen' },
    args: { overview },
    render: (args) => (
        <InertiaPage<OverviewProps>
            name="admin/overview"
            component={Overview}
            url="/admin"
            props={args}
            shared={sharedProps(adminAuth)}
        />
    ),
} satisfies Meta<typeof Overview>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** On a phone the tiles and charts stack. */
export const Phone: Story = {
    globals: { viewport: { value: 'phone', isRotated: false } },
};
