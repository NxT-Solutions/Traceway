import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, waitFor } from 'storybook/test';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';

/** Example codes in the shape Fortify gives them. Never real ones. */
const codes = Array.from(
    { length: 8 },
    (_, index) => `EXAMPLE00${index + 1}-NOTAREALCODE`,
);

/**
 * The recovery codes for two-factor log in: hidden until the rider asks, then shown in a grid to copy into a
 * password manager. Regenerate replaces them all; each code works once.
 */
const meta = {
    title: 'Components/TwoFactorRecoveryCodes',
    component: TwoFactorRecoveryCodes,
    args: {
        recoveryCodesList: codes,
        fetchRecoveryCodes: fn(async () => undefined),
        errors: [],
    },
    argTypes: {
        recoveryCodesList: {
            control: 'object',
            description:
                'The codes, each good once. Empty while they load: the card asks for them.',
        },
        fetchRecoveryCodes: {
            description:
                'Asks the server for the codes; called when the list is empty and after Regenerate.',
        },
        errors: {
            control: 'object',
            description: 'Why the codes could not be loaded.',
        },
    },
    decorators: [
        (Story) => (
            <div style={{ maxWidth: 640 }}>
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof TwoFactorRecoveryCodes>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The codes stay hidden, and the button says it would show them. */
export const Hidden: Story = {
    play: async ({ canvas, args }) => {
        await expect(
            canvas.getByRole('button', { name: /view recovery codes/i }),
        ).toHaveAttribute('aria-expanded', 'false');
        await expect(
            canvas.queryByRole('list', { name: 'Recovery codes' }),
        ).not.toBeInTheDocument();
        await expect(args.fetchRecoveryCodes).not.toHaveBeenCalled();
    },
};

/** View shows every code and Regenerate; Hide puts them away again. */
export const Shown: Story = {
    play: async ({ canvas, userEvent }) => {
        await userEvent.click(
            canvas.getByRole('button', { name: /view recovery codes/i }),
        );

        const hide = canvas.getByRole('button', {
            name: /hide recovery codes/i,
        });

        await expect(hide).toHaveAttribute('aria-expanded', 'true');
        await expect(
            canvas.getAllByRole('listitem').map((item) => item.textContent),
        ).toEqual(codes);
        await expect(
            canvas.getByRole('button', { name: /regenerate codes/i }),
        ).toHaveAccessibleDescription(/can be used once/);
    },
};

/** Hiding them again. */
export const HiddenAgain: Story = {
    play: async (context) => {
        await Shown.play?.(context);
        await context.userEvent.click(
            context.canvas.getByRole('button', {
                name: /hide recovery codes/i,
            }),
        );
        await expect(
            context.canvas.getByRole('button', {
                name: /view recovery codes/i,
            }),
        ).toHaveAttribute('aria-expanded', 'false');
    },
};

/** While the codes load: the card asks for them, and says it is loading. */
export const Loading: Story = {
    args: { recoveryCodesList: [] },
    play: async ({ canvas, args, userEvent }) => {
        await waitFor(() => expect(args.fetchRecoveryCodes).toHaveBeenCalled());
        await userEvent.click(
            canvas.getByRole('button', { name: /view recovery codes/i }),
        );
        await expect(canvas.getByRole('status')).toHaveTextContent(
            'Loading recovery codes',
        );
    },
};

export const CouldNotLoad: Story = {
    args: {
        recoveryCodesList: [],
        errors: ['Your session ended. Log in again to see your codes.'],
    },
    play: async ({ canvas, args, userEvent }) => {
        await userEvent.click(
            canvas.getByRole('button', { name: /view recovery codes/i }),
        );
        await expect(canvas.getByRole('alert')).toHaveTextContent(
            args.errors[0] ?? '',
        );
    },
};
