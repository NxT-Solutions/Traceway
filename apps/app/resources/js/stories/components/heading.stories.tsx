import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect } from 'storybook/test';
import Heading from '@/components/heading';

/**
 * The title of a settings section, with one plain line under it that says what the section is for. `small` heads
 * a card inside a section.
 */
const meta = {
    title: 'Components/Heading',
    component: Heading,
    args: {
        title: 'Profile',
        description: 'Your name and the email you log in with.',
    },
    argTypes: {
        title: { control: 'text', description: 'The section title, an h2.' },
        description: {
            control: 'text',
            description: 'One plain line under the title. Optional.',
        },
        variant: {
            control: 'inline-radio',
            options: ['default', 'small'],
            description:
                '`default` heads a section; `small` heads a card inside one.',
        },
        id: {
            control: 'text',
            description: 'An id, so a card can be named by its heading.',
        },
        focusable: {
            control: 'boolean',
            description:
                'The page can move focus here, such as after the control the rider used is gone.',
        },
    },
} satisfies Meta<typeof Heading>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A level-two heading with its line under it. */
export const Default: Story = {
    play: async ({ canvas, args }) => {
        await expect(
            canvas.getByRole('heading', { level: 2, name: args.title }),
        ).toBeVisible();

        if (args.description) {
            await expect(canvas.getByText(args.description)).toBeVisible();
        }
    },
};

export const Small: Story = {
    args: {
        variant: 'small',
        title: 'Passkeys',
        description: 'Log in with your face, fingerprint, or screen lock.',
    },
    play: Default.play,
};

export const TitleOnly: Story = {
    args: { description: undefined },
    play: async ({ canvas, canvasElement, args }) => {
        await expect(
            canvas.getByRole('heading', { level: 2, name: args.title }),
        ).toBeVisible();
        await expect(canvasElement.querySelector('p')).toBeNull();
    },
};

/** Focus can land on it from code, but Tab passes it by. */
export const Focusable: Story = {
    args: { focusable: true, id: 'profile-heading' },
    play: async ({ canvas, args }) => {
        const heading = canvas.getByRole('heading', { name: args.title });

        await expect(heading).toHaveAttribute('tabindex', '-1');
        heading.focus();
        await expect(heading).toHaveFocus();
    },
};
