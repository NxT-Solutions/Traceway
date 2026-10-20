import type { Meta, StoryObj } from '@storybook/react-vite';
import { CircleAlert, Info } from 'lucide-react';
import { expect } from 'storybook/test';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * A short note inside a form or a card. `default` says something the rider should know; `destructive` says what
 * went wrong and what to do next. An icon first, then a title, then one or two plain sentences.
 */
const meta = {
    title: 'UI/Alert',
    component: Alert,
    args: { variant: 'default' },
    argTypes: {
        variant: {
            control: 'inline-radio',
            options: ['default', 'destructive'],
            description:
                '`default` for a note, `destructive` for something that went wrong. Both are announced as an alert.',
            table: { defaultValue: { summary: 'default' } },
        },
        className: {
            control: 'text',
            description: 'Extra classes, such as a max width.',
        },
    },
    render: (args) => (
        <Alert {...args} className="max-w-md">
            <Info />
            <AlertTitle>Your route is saved</AlertTitle>
            <AlertDescription>
                Morning loop, 41.6 km, is in Saved routes. Send it to your Edge
                540 from there.
            </AlertDescription>
        </Alert>
    ),
} satisfies Meta<typeof Alert>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvas }) => {
        const alert = canvas.getByRole('alert');

        await expect(alert).toHaveTextContent('Your route is saved');
        await expect(alert).toHaveTextContent('Morning loop, 41.6 km');
    },
};

export const Destructive: Story = {
    args: { variant: 'destructive' },
    render: (args) => (
        <Alert {...args} className="max-w-md">
            <CircleAlert />
            <AlertTitle>The route could not be drawn</AlertTitle>
            <AlertDescription>
                No road or path reaches this pin. Move it closer to a road and
                try again.
            </AlertDescription>
        </Alert>
    ),
    play: async ({ canvas }) => {
        const alert = canvas.getByRole('alert');

        await expect(alert).toHaveTextContent('The route could not be drawn');
        await expect(alert).toHaveTextContent(
            'Move it closer to a road and try again.',
        );
    },
};

export const TitleOnly: Story = {
    render: (args) => (
        <Alert {...args} className="max-w-md">
            <Info />
            <AlertTitle>Route drawing is slow right now</AlertTitle>
        </Alert>
    ),
    play: async ({ canvas }) => {
        await expect(canvas.getByRole('alert')).toHaveTextContent(
            'Route drawing is slow right now',
        );
    },
};
