import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import { UserInfo } from '@/components/user-info';
import { rider } from '../support/fixtures';

/**
 * The rider's picture or initials and their name, with their email when there is room, as the account menu
 * shows them.
 */
const meta = {
    title: 'Components/UserInfo',
    component: UserInfo,
    args: { user: rider },
    argTypes: {
        user: {
            control: 'object',
            description: 'The rider: name, email and picture (`avatar`).',
        },
        showEmail: {
            control: 'boolean',
            description: 'Show the email under the name, where there is room.',
        },
    },
    decorators: [
        // As wide as the account menu, or `parameters.width` for a narrow one.
        (Story, { parameters }) => (
            <div
                className="flex items-center gap-2 rounded-lg border p-2"
                style={{ maxWidth: Number(parameters.width ?? 320) }}
            >
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof UserInfo>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Initials stand in for a picture; the email stays out. */
export const NameOnly: Story = {
    play: async ({ canvas, args }) => {
        await expect(canvas.getByText(args.user.name)).toBeVisible();
        await expect(canvas.getByText('SR')).toBeVisible();
        await expect(
            canvas.queryByText(args.user.email),
        ).not.toBeInTheDocument();
    },
};

export const WithEmail: Story = {
    args: { showEmail: true },
    play: async ({ canvas, args }) => {
        await expect(canvas.getByText(args.user.email)).toBeVisible();
    },
};

/** In a narrow menu, a long name and email are cut short with an ellipsis, and the whole text is still there. */
export const LongName: Story = {
    parameters: { width: 220 },
    args: {
        showEmail: true,
        user: {
            ...rider,
            name: 'Alexandra Vanderstraeten-Peeters',
            email: 'alexandra.vanderstraeten@example.com',
        },
    },
    play: async ({ canvas, args }) => {
        const name = canvas.getByText(args.user.name);

        await expect(name).toHaveClass('truncate');
        await expect(name.scrollWidth).toBeGreaterThan(name.clientWidth);
    },
};
