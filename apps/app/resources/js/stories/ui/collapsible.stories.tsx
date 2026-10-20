import type { Meta, StoryObj } from '@storybook/react-vite';
import { ChevronsUpDown } from 'lucide-react';
import { expect, fn } from 'storybook/test';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

/**
 * Hides detail until the rider asks for it, such as the turns of a route. The trigger says what it shows.
 */
const meta = {
    title: 'UI/Collapsible',
    component: Collapsible,
    args: { onOpenChange: fn() },
    argTypes: {
        open: {
            control: 'boolean',
            description:
                'Controlled state. Leave unset to let it keep its own.',
        },
        defaultOpen: {
            control: 'boolean',
            description: 'Whether the detail starts shown.',
        },
        disabled: {
            control: 'boolean',
            description: 'The trigger does nothing.',
        },
        onOpenChange: {
            action: 'open changed',
            description:
                'Called with `true` when the detail shows, `false` when it hides.',
        },
    },
    render: (args) => (
        <Collapsible {...args} className="flex max-w-sm flex-col gap-2">
            <div className="flex items-center justify-between gap-4">
                <p className="text-sm font-medium">Turns (4)</p>
                <CollapsibleTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label="Show turns">
                        <ChevronsUpDown />
                    </Button>
                </CollapsibleTrigger>
            </div>
            <div className="rounded-md border px-4 py-2 text-sm">
                Left onto Boerenkrijgsingel · 0.4 km
            </div>
            <CollapsibleContent className="flex flex-col gap-2">
                <div className="rounded-md border px-4 py-2 text-sm">
                    Right onto Grote Markt · 2.1 km
                </div>
                <div className="rounded-md border px-4 py-2 text-sm">
                    Bear left onto Netestraat · 9.8 km
                </div>
                <div className="rounded-md border px-4 py-2 text-sm">
                    Roundabout, second exit · 18.3 km
                </div>
            </CollapsibleContent>
        </Collapsible>
    ),
} satisfies Meta<typeof Collapsible>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Closed: Story = {
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Show turns' }),
        ).toHaveAttribute('aria-expanded', 'false');
        await expect(
            canvas.queryByText('Right onto Grote Markt · 2.1 km'),
        ).not.toBeInTheDocument();
    },
};

export const Open: Story = {
    args: { defaultOpen: true },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Show turns' }),
        ).toHaveAttribute('aria-expanded', 'true');
        await expect(
            canvas.getByText('Roundabout, second exit · 18.3 km'),
        ).toBeVisible();
    },
};

/** A click shows the turns; Enter on the same button hides them again. */
export const ShowAndHide: Story = {
    play: async ({ args, canvas, userEvent }) => {
        const trigger = canvas.getByRole('button', { name: 'Show turns' });

        await userEvent.click(trigger);
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(
            canvas.getByText('Bear left onto Netestraat · 9.8 km'),
        ).toBeVisible();
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(true);

        await userEvent.keyboard('{Enter}');
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(false);
    },
};
