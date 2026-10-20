import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn } from 'storybook/test';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const send = fn().mockName('Send to device');
const open = fn().mockName('Open');

/**
 * A panel that groups one thing: a title, a line that says what it is for, the content, and at most one filled
 * action.
 */
const meta = {
    title: 'UI/Card',
    component: Card,
    argTypes: {
        className: {
            control: 'text',
            description: 'Extra classes, such as a max width.',
        },
    },
    beforeEach: () => {
        send.mockClear();
        open.mockClear();
    },
    render: (args) => (
        <Card {...args} className="max-w-sm">
            <CardHeader>
                <CardTitle>Morning loop</CardTitle>
                <CardDescription>
                    Road, from Westerlo. Saved on 12 October.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-sm">41.6 km · 180 m up · mostly paved</p>
            </CardContent>
            <CardFooter className="gap-2">
                <Button onClick={send}>Send to device</Button>
                <Button variant="ghost" onClick={open}>
                    Open
                </Button>
            </CardFooter>
        </Card>
    ),
} satisfies Meta<typeof Card>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvas, userEvent }) => {
        await expect(canvas.getByText('Morning loop')).toBeVisible();

        // The filled action comes first, then the quiet one.
        await userEvent.tab();
        await expect(
            canvas.getByRole('button', { name: 'Send to device' }),
        ).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(send).toHaveBeenCalledOnce();
        await userEvent.tab();
        await expect(
            canvas.getByRole('button', { name: 'Open' }),
        ).toHaveFocus();
        await userEvent.click(canvas.getByRole('button', { name: 'Open' }));
        await expect(open).toHaveBeenCalledOnce();
    },
};

export const HeaderOnly: Story = {
    render: (args) => (
        <Card {...args} className="max-w-sm">
            <CardHeader>
                <CardTitle>No saved routes yet</CardTitle>
                <CardDescription>
                    Plan a route on the map and save it. It shows up here.
                </CardDescription>
            </CardHeader>
        </Card>
    ),
    play: async ({ canvas }) => {
        await expect(canvas.getByText('No saved routes yet')).toBeVisible();
        await expect(canvas.queryByRole('button')).not.toBeInTheDocument();
    },
};
