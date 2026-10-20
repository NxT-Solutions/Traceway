import { linkTo } from '@storybook/addon-links';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import AlertError from '@/components/alert-error';

/**
 * Every error of a form in one alert, each only once. Without a title it says "Something went wrong." An error that
 * belongs to one field goes under that field instead, with InputError.
 */
const meta = {
    title: 'Components/AlertError',
    component: AlertError,
    args: {
        title: 'The codes could not be loaded',
        errors: ['Your session ended. Log in again to see your codes.'],
    },
    argTypes: {
        title: {
            control: 'text',
            description:
                'The first line, in bold. Leave it empty for "Something went wrong."',
        },
        errors: {
            control: 'object',
            description:
                'The messages, in plain words that say how to fix it. Repeats show once.',
        },
    },
    render: (args) => (
        <div className="grid max-w-md gap-3">
            <AlertError {...args} />
            <button
                type="button"
                className="justify-self-start text-sm underline underline-offset-4"
                onClick={linkTo('Components/InputError', 'Default')}
            >
                One field's error: InputError
            </button>
        </div>
    ),
} satisfies Meta<typeof AlertError>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Announced as an alert, with its title and the message. */
export const Default: Story = {
    play: async ({ canvas, args }) => {
        const alert = canvas.getByRole('alert');

        await expect(alert).toHaveTextContent(args.title ?? '');
        await expect(alert).toHaveTextContent(args.errors[0] ?? '');
    },
};

/** Repeated errors show once. */
export const SeveralErrors: Story = {
    args: {
        title: undefined,
        errors: [
            'The route needs a start.',
            'A device name is 1 to 15 characters.',
            'The route needs a start.',
        ],
    },
    play: async ({ canvas }) => {
        await expect(canvas.getByRole('alert')).toHaveTextContent(
            'Something went wrong.',
        );
        await expect(canvas.getAllByRole('listitem')).toHaveLength(2);
        await expect(
            canvas.getAllByText('The route needs a start.'),
        ).toHaveLength(1);
    },
};
