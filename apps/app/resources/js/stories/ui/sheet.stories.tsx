import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, waitFor, within } from 'storybook/test';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

/**
 * A panel that slides in from an edge. The planner uses it on a phone for a setting with many choices, such as
 * the sport.
 */
const meta = {
    title: 'UI/Sheet',
    component: Sheet,
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
                'Called with `true` when it opens and `false` when it closes: Close, Escape, or a tap outside.',
        },
    },
    parameters: { docs: { story: { inline: false, iframeHeight: 480 } } },
    render: (args) => (
        <Sheet {...args}>
            <SheetTrigger asChild>
                <Button variant="outline">Route settings</Button>
            </SheetTrigger>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>Morning loop</SheetTitle>
                    <SheetDescription>
                        Road, 41.6 km from Westerlo, 180 m up.
                    </SheetDescription>
                </SheetHeader>
                <div className="grid gap-2 px-4 text-sm">
                    <p>Quiet roads where there are some.</p>
                    <p>Mostly paved, with 2 km of gravel.</p>
                    <Button
                        variant="outline"
                        className="justify-self-start"
                        onClick={linkTo('UI/Select', 'Open')}
                    >
                        Change sport
                    </Button>
                </div>
                <SheetFooter>
                    <Button>Save route</Button>
                    <SheetClose asChild>
                        <Button variant="ghost">Close</Button>
                    </SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    ),
} satisfies Meta<typeof Sheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Open: Story = {
    play: async ({ canvasElement }) => {
        const sheet = await within(canvasElement.ownerDocument.body).findByRole(
            'dialog',
            { name: 'Morning loop' },
        );

        await expect(sheet).toHaveAccessibleDescription(
            'Road, 41.6 km from Westerlo, 180 m up.',
        );
        await waitFor(() =>
            expect(
                within(sheet).getByRole('button', { name: 'Save route' }),
            ).toBeVisible(),
        );
        await waitFor(() =>
            expect(sheet).toContainElement(
                canvasElement.ownerDocument.activeElement as HTMLElement,
            ),
        );
    },
};

/** Closed: Route settings opens it, and its Close button shuts it again. */
export const Closed: Story = {
    args: { defaultOpen: false },
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const trigger = canvas.getByRole('button', { name: 'Route settings' });

        await userEvent.click(trigger);
        const sheet = await page.findByRole('dialog', { name: 'Morning loop' });
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(true);

        // Two buttons say Close: the corner cross and the one in the footer.
        const footer = sheet.querySelector<HTMLElement>(
            '[data-slot="sheet-footer"]',
        );
        await expect(footer).not.toBeNull();
        await userEvent.click(
            within(footer as HTMLElement).getByRole('button', {
                name: 'Close',
            }),
        );
        await waitFor(() =>
            expect(page.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(false);
        await expect(trigger).toHaveFocus();
    },
};

/** Escape closes it too. */
export const EscapeCloses: Story = {
    play: async ({ args, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);

        await page.findByRole('dialog', { name: 'Morning loop' });
        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(page.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await expect(args.onOpenChange).toHaveBeenCalledWith(false);
    },
};
