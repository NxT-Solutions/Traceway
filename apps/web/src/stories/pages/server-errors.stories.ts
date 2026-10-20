import { expect, within } from 'storybook/test';
import { errorPage, staticErrors } from '../../errors';
import ServerErrorPage from '../../pages/errors/[status].astro';
import { shows } from '../support/play';
import type { Meta, StoryObj } from '../support/types';

/**
 * The pages nginx serves when it answers with an error itself, as when the app is down
 * (/errors/<status>/, one per status in `staticErrors`). Each is the error page for its status.
 */
const meta = {
    title: 'Pages/Server errors',
    component: ServerErrorPage,
    parameters: {
        sheets: 'errors',
        bodyClass: ({ status }: { status?: number }) => {
            const page = errorPage(Number(status ?? 500));

            return `is-${page.athlete}${page.jolt ? ' jolt' : ''}`;
        },
    },
    args: { status: 500 },
    argTypes: {
        status: {
            description: 'The status nginx answered with.',
            control: 'select',
            options: staticErrors,
        },
    },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta.args>;

export const ServerError: Story = {
    name: '500',
    play: async ({ canvasElement }) => {
        await shows(within(canvasElement).getByRole('heading', {
                level: 1,
                name: 'We hit a pothole',
            }));
    },
};

export const BadGateway: Story = { name: '502', args: { status: 502 } };

export const Maintenance: Story = { name: '503', args: { status: 503 } };

export const GatewayTimeout: Story = { name: '504', args: { status: 504 } };

export const TooLarge: Story = { name: '413', args: { status: 413 } };
