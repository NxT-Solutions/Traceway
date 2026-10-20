import type { Meta, StoryObj } from '@storybook/react-vite';
import { Download } from 'lucide-react';
import { expect, fn, waitFor, within } from 'storybook/test';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A few words beside an icon button, on hover or focus. The button still needs its own `aria-label`: a tooltip
 * never carries anything the rider must read.
 */
const meta = {
    title: 'UI/Tooltip',
    component: Tooltip,
    args: { defaultOpen: true, onOpenChange: fn() },
    argTypes: {
        open: {
            control: 'boolean',
            description:
                'Controlled state. Leave unset to let it keep its own.',
        },
        defaultOpen: {
            control: 'boolean',
            description: 'Whether it starts shown.',
        },
        delayDuration: {
            control: { type: 'number', min: 0, step: 100 },
            description:
                'How long the pointer rests before it shows, in ms. The app shows it at once.',
        },
        disableHoverableContent: {
            control: 'boolean',
            description:
                'Close as soon as the pointer leaves the trigger, even towards the tip.',
        },
        onOpenChange: {
            action: 'open changed',
            description:
                'Called with `true` when it shows and `false` when it hides.',
        },
    },
    parameters: {
        layout: 'centered',
        docs: { story: { inline: false, iframeHeight: 200 } },
    },
    decorators: [
        (Story) => (
            <TooltipProvider delayDuration={0}>
                <div className="p-12">
                    <Story />
                </div>
            </TooltipProvider>
        ),
    ],
    render: (args) => (
        <Tooltip {...args}>
            <TooltipTrigger asChild>
                <Button variant="outline" size="icon" aria-label="Download GPX">
                    <Download />
                </Button>
            </TooltipTrigger>
            <TooltipContent>Download GPX</TooltipContent>
        </Tooltip>
    ),
} satisfies Meta<typeof Tooltip>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Open: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            await within(canvasElement.ownerDocument.body).findByRole(
                'tooltip',
            ),
        ).toHaveTextContent('Download GPX');
    },
};

/** Hidden until the pointer rests on the button or the keyboard reaches it; Escape hides it. */
export const OnHover: Story = {
    args: { defaultOpen: false },
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const button = canvas.getByRole('button', { name: 'Download GPX' });

        await expect(page.queryByRole('tooltip')).not.toBeInTheDocument();
        await userEvent.hover(button);
        await expect(await page.findByRole('tooltip')).toHaveTextContent(
            'Download GPX',
        );
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(true);

        await userEvent.unhover(button);
        await waitFor(() =>
            expect(page.queryByRole('tooltip')).not.toBeInTheDocument(),
        );

        // The keyboard shows it too, and Escape dismisses it (WCAG 1.4.13).
        await userEvent.tab();
        await expect(button).toHaveFocus();
        await expect(await page.findByRole('tooltip')).toBeInTheDocument();
        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(page.queryByRole('tooltip')).not.toBeInTheDocument(),
        );
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(false);
    },
};
