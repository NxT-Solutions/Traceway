import type { Meta, StoryObj } from '@storybook/react-vite';
import { toast } from 'sonner';
import { expect, waitFor, within } from 'storybook/test';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';

/**
 * The toaster at the bottom right. It follows Paper and Ink, uses Outfit, and shows the flash message the server
 * sends after a form. Press a button to send one.
 */
const meta = {
    title: 'UI/Sonner',
    component: Toaster,
    argTypes: {
        position: {
            control: 'select',
            options: [
                'top-left',
                'top-center',
                'top-right',
                'bottom-left',
                'bottom-center',
                'bottom-right',
            ],
            description: 'Where toasts stack. The app keeps them bottom right.',
            table: { defaultValue: { summary: 'bottom-right' } },
        },
        expand: {
            control: 'boolean',
            description: 'Show every toast in full instead of a stack.',
        },
        closeButton: {
            control: 'boolean',
            description: 'Give each toast its own close button.',
        },
        richColors: {
            control: 'boolean',
            description: 'Tint success, warning and error toasts.',
        },
        duration: {
            control: { type: 'number', min: 1000, step: 500 },
            description: 'How long a toast stays, in ms.',
            table: { defaultValue: { summary: '4000' } },
        },
        visibleToasts: {
            control: { type: 'number', min: 1, max: 9 },
            description: 'How many toasts show at once.',
            table: { defaultValue: { summary: '3' } },
        },
    },
    parameters: { docs: { story: { inline: false, iframeHeight: 320 } } },
    render: (args) => (
        <div className="flex h-64 flex-wrap content-start gap-3">
            <Button onClick={() => toast.success('Morning loop is saved.')}>
                Save route
            </Button>
            <Button
                variant="outline"
                onClick={() => toast.info('Sent to your Edge 540.')}
            >
                Send to device
            </Button>
            <Button
                variant="secondary"
                onClick={() =>
                    toast.warning('Route drawing is slow right now.')
                }
            >
                Warn
            </Button>
            <Button
                variant="destructive"
                onClick={() =>
                    toast.error('Wahoo did not answer. Try again in a minute.')
                }
            >
                Fail
            </Button>
            <Toaster {...args} />
        </div>
    ),
} satisfies Meta<typeof Toaster>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Saving a route says so in a toast, in the notifications region. */
export const Saved: Story = {
    play: async ({ canvas, canvasElement, userEvent }) => {
        await userEvent.click(
            canvas.getByRole('button', { name: 'Save route' }),
        );

        const page = within(canvasElement.ownerDocument.body);
        const message = await page.findByText('Morning loop is saved.');
        await waitFor(() => expect(message).toBeVisible());
        await expect(
            page.getByRole('region', { name: /Notifications/ }),
        ).toBeInTheDocument();
    },
};

/** A failure says what went wrong and what to do. */
export const Failed: Story = {
    play: async ({ canvas, canvasElement, userEvent }) => {
        await userEvent.click(canvas.getByRole('button', { name: 'Fail' }));
        const message = await within(
            canvasElement.ownerDocument.body,
        ).findByText('Wahoo did not answer. Try again in a minute.');
        await waitFor(() => expect(message).toBeVisible());
    },
};
