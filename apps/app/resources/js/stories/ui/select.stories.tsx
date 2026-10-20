import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, waitFor, within } from 'storybook/test';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * One choice from a short list, such as the sport of a route. Group long lists, and name the field with a label.
 */
const meta = {
    title: 'UI/Select',
    component: Select,
    args: {
        defaultValue: 'gravel',
        onValueChange: fn(),
        onOpenChange: fn(),
    },
    argTypes: {
        value: {
            control: 'select',
            options: [
                'run',
                'trail_run',
                'walk',
                'hike',
                'road',
                'gravel',
                'mtb',
            ],
            description:
                'Controlled choice. Leave unset to let it keep its own.',
        },
        defaultValue: {
            control: 'select',
            options: [
                'run',
                'trail_run',
                'walk',
                'hike',
                'road',
                'gravel',
                'mtb',
            ],
            description:
                'The choice it starts with. Unset shows the placeholder.',
        },
        open: {
            control: 'boolean',
            description: 'Controlled open state.',
        },
        defaultOpen: {
            control: 'boolean',
            description: 'Whether the list starts open.',
        },
        disabled: {
            control: 'boolean',
            description: 'Turned off.',
        },
        required: {
            control: 'boolean',
            description: 'The form cannot be sent without a choice.',
        },
        name: {
            control: 'text',
            description: 'The form field name, such as `sport`.',
        },
        onValueChange: {
            action: 'value changed',
            description: 'Called with the value of the new choice.',
        },
        onOpenChange: {
            action: 'open changed',
            description:
                'Called with `true` when the list opens and `false` when it closes.',
        },
    },
    parameters: { docs: { story: { inline: false, iframeHeight: 400 } } },
    render: (args) => (
        <div className="grid h-80 max-w-xs content-start gap-2">
            <Label htmlFor="sport">Sport</Label>
            <Select {...args}>
                <SelectTrigger id="sport" className="w-56">
                    <SelectValue placeholder="Pick a sport" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>On foot</SelectLabel>
                        <SelectItem value="run">Run</SelectItem>
                        <SelectItem value="trail_run">Trail run</SelectItem>
                        <SelectItem value="walk">Walk</SelectItem>
                        <SelectItem value="hike">Hike</SelectItem>
                    </SelectGroup>
                    <SelectSeparator />
                    <SelectGroup>
                        <SelectLabel>On a bike</SelectLabel>
                        <SelectItem value="road">Road</SelectItem>
                        <SelectItem value="gravel">Gravel</SelectItem>
                        <SelectItem value="mtb">Mountain bike</SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>
    ),
} satisfies Meta<typeof Select>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Closed: the rider opens the list, picks Trail run, and the field shows it. */
export const Closed: Story = {
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const field = canvas.getByRole('combobox', { name: 'Sport' });

        await expect(field).toHaveTextContent('Gravel');
        await userEvent.click(field);
        const list = await page.findByRole('listbox');
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(true);

        await userEvent.click(
            within(list).getByRole('option', { name: 'Trail run' }),
        );
        await waitFor(() =>
            expect(page.queryByRole('listbox')).not.toBeInTheDocument(),
        );
        await expect(field).toHaveTextContent('Trail run');
        await expect(args.onValueChange).toHaveBeenCalledWith('trail_run');
        await expect(args.onOpenChange).toHaveBeenLastCalledWith(false);
    },
};

export const Open: Story = {
    args: { defaultOpen: true },
    parameters: {
        // While open, Radix Select hides the rest of the page from screen readers with aria-hidden (its
        // hideOthers), and the hidden page still holds the trigger and other focusable things. Focus is held in the
        // list until it closes, so nothing hidden can be reached; axe's aria-hidden-focus cannot see that. Upstream,
        // and only while open, so that one rule is off here; Closed and Empty check the whole field.
        a11y: {
            config: { rules: [{ id: 'aria-hidden-focus', enabled: false }] },
        },
    },
    play: async ({ canvasElement, userEvent }) => {
        const list = await within(canvasElement.ownerDocument.body).findByRole(
            'listbox',
        );

        await expect(
            within(list).getByRole('option', { name: 'Gravel' }),
        ).toHaveAttribute('aria-selected', 'true');
        await expect(within(list).getAllByRole('option')).toHaveLength(7);
        await waitFor(() =>
            expect(within(list).getByText('On foot')).toBeVisible(),
        );

        // The arrow keys move through the choices.
        await waitFor(() =>
            expect(
                within(list).getByRole('option', { name: 'Gravel' }),
            ).toHaveFocus(),
        );
        await userEvent.keyboard('{ArrowDown}');
        await expect(
            within(list).getByRole('option', { name: 'Mountain bike' }),
        ).toHaveFocus();
    },
};

/** Nothing chosen yet: the placeholder asks. From the keyboard, Enter opens the list and Escape closes it. */
export const Empty: Story = {
    args: { defaultValue: undefined },
    play: async ({ args, canvas, canvasElement, userEvent }) => {
        const page = within(canvasElement.ownerDocument.body);
        const field = canvas.getByRole('combobox', { name: 'Sport' });

        await expect(field).toHaveTextContent('Pick a sport');
        await userEvent.tab();
        await expect(field).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        const list = await page.findByRole('listbox');
        await waitFor(() => expect(list).toBeVisible());
        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(page.queryByRole('listbox')).not.toBeInTheDocument(),
        );
        await expect(field).toHaveFocus();
        await expect(args.onValueChange).not.toHaveBeenCalled();
    },
};
