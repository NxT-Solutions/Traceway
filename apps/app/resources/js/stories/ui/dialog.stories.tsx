import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, waitFor, within } from 'storybook/test';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const remove = fn().mockName('Remove passkey');

/**
 * Asks before something that cannot be undone, or holds a short task on its own. The title is the question, the
 * description says what happens, and the buttons say what they do: "Cancel", "Remove passkey".
 */
const meta = {
    title: 'UI/Dialog',
    component: Dialog,
    args: { defaultOpen: true, onOpenChange: fn() },
    argTypes: {
        open: {
            control: 'boolean',
            description:
                'Controlled state. Leave unset to let it keep its own.',
        },
        defaultOpen: {
            control: 'boolean',
            description: 'Whether it starts open.',
        },
        modal: {
            control: 'boolean',
            description:
                'Keeps focus inside and the page behind out of reach while open.',
            table: { defaultValue: { summary: 'true' } },
        },
        onOpenChange: {
            action: 'open changed',
            description:
                'Called with `true` when it opens and `false` when it closes: Cancel, the close button, Escape, or a click outside.',
        },
    },
    parameters: {
        layout: 'centered',
        docs: { story: { inline: false, iframeHeight: 360 } },
    },
    beforeEach: () => {
        remove.mockClear();
    },
    render: (args) => (
        <Dialog {...args}>
            <DialogTrigger asChild>
                <Button variant="outline">Remove passkey</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove passkey</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove the "MacBook Air"
                        passkey? You will no longer be able to use it to log in.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" onClick={remove}>
                        Remove passkey
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    ),
} satisfies Meta<typeof Dialog>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Open: Story = {
    play: async ({ canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const dialog = await page.findByRole('dialog', {
            name: 'Remove passkey',
        });

        await expect(dialog).toHaveAccessibleDescription(
            /You will no longer be able to use it to log in./,
        );
        // Focus starts inside and Tab keeps it there.
        await waitFor(() =>
            expect(dialog).toContainElement(
                canvasElement.ownerDocument.activeElement as HTMLElement,
            ),
        );

        for (let press = 0; press < 4; press++) {
            await userEvent.tab();
            await expect(dialog).toContainElement(
                canvasElement.ownerDocument.activeElement as HTMLElement,
            );
        }
    },
};

/** Closed, the trigger is all there is. Open it, then Escape closes it and focus goes back to the trigger. */
export const Closed: Story = {
    args: { defaultOpen: false },
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const trigger = canvas.getByRole('button', { name: 'Remove passkey' });

        await expect(page.queryByRole('dialog')).not.toBeInTheDocument();

        await userEvent.click(trigger);
        const dialog = await page.findByRole('dialog');
        await waitFor(() => expect(dialog).toBeVisible());
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(true);

        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(page.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(false);
        await expect(trigger).toHaveFocus();
    },
};

/** Cancel closes it without removing anything. */
export const Cancelled: Story = {
    play: async ({ args, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const dialog = await page.findByRole('dialog');

        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Cancel' }),
        );
        await waitFor(() =>
            expect(page.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await expect(args.onOpenChange).toHaveBeenCalledWith(false);
        await expect(remove).not.toHaveBeenCalled();
    },
};

/** The rider confirms: the destructive button does its work. */
export const Confirmed: Story = {
    play: async ({ canvasElement, userEvent }) => {
        const dialog = await within(
            canvasElement.ownerDocument.body,
        ).findByRole('dialog');

        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Remove passkey' }),
        );
        await expect(remove).toHaveBeenCalledOnce();
    },
};
