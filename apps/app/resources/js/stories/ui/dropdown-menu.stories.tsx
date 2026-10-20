import type { Meta, StoryObj } from '@storybook/react-vite';
import { Download, LogOut, Settings } from 'lucide-react';
import { expect, fn, waitFor, within } from 'storybook/test';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const download = fn().mockName('Download GPX');

/**
 * A short list of actions behind one button, such as the account menu. Group related items, and keep the one that
 * leaves (Log out) last.
 */
const meta = {
    title: 'UI/DropdownMenu',
    component: DropdownMenu,
    args: { defaultOpen: true, modal: false, onOpenChange: fn() },
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
                'Keeps the page behind out of reach while open. The account menu is not modal.',
            table: { defaultValue: { summary: 'true' } },
        },
        dir: {
            control: 'inline-radio',
            options: ['ltr', 'rtl'],
            description: 'Reading direction, for the arrow keys.',
        },
        onOpenChange: {
            action: 'open changed',
            description:
                'Called with `true` when it opens and `false` when it closes.',
        },
    },
    parameters: {
        layout: 'centered',
        docs: { story: { inline: false, iframeHeight: 320 } },
    },
    beforeEach: () => {
        download.mockClear();
    },
    render: (args) => (
        <div className="h-64">
            <DropdownMenu {...args}>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">Sam Rivera</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-56" align="start">
                    <DropdownMenuLabel>sam@example.com</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem>
                            <Settings />
                            Settings
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={download}>
                            <Download />
                            Download GPX
                            <DropdownMenuShortcut>⌘D</DropdownMenuShortcut>
                        </DropdownMenuItem>
                        <DropdownMenuCheckboxItem checked>
                            Quiet roads
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant="destructive">
                        <LogOut />
                        Log out
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    ),
} satisfies Meta<typeof DropdownMenu>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Open: Story = {
    play: async ({ canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const menu = await page.findByRole('menu');
        const items = [
            ...menu.querySelectorAll<HTMLElement>('[role^="menuitem"]'),
        ];

        await expect(items.map((item) => item.textContent)).toEqual([
            'Settings',
            'Download GPX⌘D',
            'Quiet roads',
            'Log out',
        ]);
        await expect(
            within(menu).getByRole('menuitemcheckbox', { name: 'Quiet roads' }),
        ).toBeChecked();

        // The arrow keys walk the items, and End jumps to the last one.
        await userEvent.keyboard('{ArrowDown}');
        await expect(items[0]).toHaveFocus();
        await userEvent.keyboard('{ArrowDown}');
        await expect(items[1]).toHaveFocus();
        await userEvent.keyboard('{End}');
        await expect(items[3]).toHaveFocus();
    },
};

/** Closed: open it from the button, then Escape closes it and focus goes back to the button. */
export const Closed: Story = {
    args: { defaultOpen: false },
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const trigger = canvas.getByRole('button', { name: 'Sam Rivera' });

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await userEvent.click(trigger);
        const menu = await page.findByRole('menu');
        await waitFor(() => expect(menu).toBeVisible());
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(true);

        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(page.queryByRole('menu')).not.toBeInTheDocument(),
        );
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(false);
        await expect(trigger).toHaveFocus();
    },
};

/** Choosing an item does it and closes the menu. */
export const ChooseAnItem: Story = {
    play: async ({ args, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);

        await userEvent.click(
            await page.findByRole('menuitem', { name: /Download GPX/ }),
        );
        await expect(download).toHaveBeenCalledOnce();
        await waitFor(() =>
            expect(page.queryByRole('menu')).not.toBeInTheDocument(),
        );
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(false);
    },
};
