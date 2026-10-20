import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * The name of a field. Point it at the field with `htmlFor`, so a click on the words focuses the field. A label
 * beside a turned-off field fades with it.
 */
const meta = {
    title: 'UI/Label',
    component: Label,
    args: { children: 'Email', htmlFor: 'email' },
    argTypes: {
        children: {
            control: 'text',
            description:
                'The words. The field’s accessible name is exactly this.',
        },
        htmlFor: {
            control: 'text',
            description: 'The id of the field it names.',
        },
    },
    render: (args) => (
        <div className="grid max-w-sm gap-2">
            <Label {...args} />
            <Input id="email" type="email" placeholder="sam@example.com" />
        </div>
    ),
} satisfies Meta<typeof Label>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    play: async ({ canvas, userEvent }) => {
        const field = canvas.getByRole('textbox', { name: 'Email' });

        await userEvent.click(canvas.getByText('Email'));
        await expect(field).toHaveFocus();
    },
};

export const BesideADisabledCheckbox: Story = {
    args: { children: 'Send to Wahoo', htmlFor: 'send-wahoo' },
    render: (args) => (
        <div className="flex items-center gap-3">
            <Checkbox id="send-wahoo" disabled />
            <Label {...args} />
        </div>
    ),
    play: async ({ canvas }) => {
        const box = canvas.getByRole('checkbox', { name: 'Send to Wahoo' });

        await expect(box).toBeDisabled();
        await expect(box).not.toBeChecked();
    },
};
