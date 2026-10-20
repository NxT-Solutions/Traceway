import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import InputError from '@/components/input-error';

/**
 * What is wrong with a field, right under it, in words that say how to fix it. Nothing at all when there is no
 * message. Errors that belong to no one field go together in AlertError.
 */
const meta = {
    title: 'Components/InputError',
    component: InputError,
    args: { message: 'A device name is 1 to 15 characters.' },
    argTypes: {
        message: {
            control: 'text',
            description:
                'What is wrong and how to fix it. Leave it empty and nothing renders.',
        },
        id: {
            control: 'text',
            description:
                'An id, so the field can point at it with `aria-describedby`.',
        },
        className: { control: 'text', description: 'Extra classes.' },
    },
    render: (args) => (
        <div className="grid max-w-md gap-3">
            <InputError {...args} />
            <button
                type="button"
                className="justify-self-start text-sm underline underline-offset-4"
                onClick={linkTo('Components/AlertError', 'Several Errors')}
            >
                Errors for the whole form: AlertError
            </button>
        </div>
    ),
} satisfies Meta<typeof InputError>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The message, as plain text under the field. */
export const Default: Story = {
    play: async ({ canvas, args }) => {
        await expect(canvas.getByText(args.message ?? '')).toBeVisible();
    },
};

export const Password: Story = {
    args: { message: 'That password is not right. Try again.' },
    play: Default.play,
};

/** No message: nothing renders. */
export const Empty: Story = {
    args: { message: undefined },
    play: async ({ canvasElement }) => {
        await expect(canvasElement.querySelector('p')).toBeNull();
    },
};
