import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, waitFor } from 'storybook/test';
import ReleaseBell from '@/components/release-bell';
import { useAppearance } from '@/hooks/use-appearance';
import type { WhatsNew } from '@/types';
import { rider } from '../support/fixtures';
import { InertiaPage, sharedProps } from '../support/inertia-page';

/** The bell itself, not the toast's "See what's new". */
const bell = /^what's new/i;

const releases: WhatsNew = {
    unread: 2,
    items: [
        {
            id: 12,
            tag: 'v0.11.1',
            name: 'The new-version note looks like Traceway',
            published_at: '2026-10-18T09:00:00Z',
            headline:
                'The note that a new version is out now uses the planner colours.',
        },
        {
            id: 11,
            tag: 'v0.11.0',
            name: 'Log in with the accounts you already have',
            published_at: '2026-10-14T09:00:00Z',
            headline:
                'Google, Wahoo, Ride with GPS and more. Manage them under Settings, Connections.',
        },
    ],
};

/** The header of the settings pages, where the bell sits beside the account. */
function SettingsHeader() {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-settings"
            data-theme={resolvedAppearance}
            style={{ minHeight: 420 }}
        >
            <header className="hdr">
                <div className="wrap">
                    <span className="brand">Traceway</span>
                    <div className="hdr-r">
                        <ReleaseBell />
                    </div>
                </div>
            </header>
        </div>
    );
}

function Bell({ whatsNew }: { whatsNew: WhatsNew | null }) {
    return (
        <InertiaPage
            name="dashboard"
            component={SettingsHeader}
            props={{}}
            shared={{
                ...sharedProps({ user: rider, can: null }),
                whatsNew,
            }}
        />
    );
}

/**
 * What's new: a bell with the count of releases the rider has not read, and a short list of them. On a desktop
 * it also says once, in a toast, that a new version is out. Nothing at all for a guest. Open it with a click.
 */
const meta = {
    title: 'Components/ReleaseBell',
    component: Bell,
    args: { whatsNew: releases },
    argTypes: {
        whatsNew: {
            control: 'object',
            description:
                'How many releases the rider has not read, and the newest few. null for a guest: no bell.',
        },
    },
    parameters: { layout: 'fullscreen' },
} satisfies Meta<typeof Bell>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The bell says how many are unread, to the eye and to a screen reader. */
export const Unread: Story = {
    play: async ({ canvas }) => {
        const button = await canvas.findByRole('button', {
            name: "What's new, 2 unread",
        });

        await expect(button).toHaveAttribute('aria-expanded', 'false');
        await expect(button).toHaveTextContent('2');
    },
};

/** The list, opened from the bell: each release is a link to its notes. */
export const Open: Story = {
    play: async ({ canvas, userEvent }) => {
        const button = await canvas.findByRole('button', { name: bell });

        await userEvent.click(button);
        await expect(button).toHaveAttribute('aria-expanded', 'true');

        const panel = canvas.getByRole('region', { name: "What's new" });

        await expect(panel).toBeVisible();
        await expect(
            canvas.getByRole('link', {
                name: /Log in with the accounts you already have/,
            }),
        ).toBeVisible();
        await expect(
            canvas.getByRole('button', { name: 'Mark all as read' }),
        ).toBeVisible();
    },
};

/** Escape closes the list and puts focus back on the bell. */
export const ClosedWithEscape: Story = {
    play: async (context) => {
        await Open.play?.(context);
        const { canvas, userEvent } = context;

        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(
                canvas.queryByRole('region', { name: "What's new" }),
            ).not.toBeInTheDocument(),
        );
        await expect(canvas.getByRole('button', { name: bell })).toHaveFocus();
    },
};

/** Ten or more unread show as 9+. */
export const ManyUnread: Story = {
    args: { whatsNew: { ...releases, unread: 14 } },
    play: async ({ canvas }) => {
        await expect(
            await canvas.findByRole('button', {
                name: "What's new, 9+ unread",
            }),
        ).toHaveTextContent('9+');
    },
};

/** Nothing unread: no count, and the list says so. */
export const UpToDate: Story = {
    args: { whatsNew: { unread: 0, items: [] } },
    play: async ({ canvas, userEvent }) => {
        await userEvent.click(
            await canvas.findByRole('button', { name: "What's new" }),
        );
        await expect(canvas.getByText("You're up to date.")).toBeVisible();
        await expect(
            canvas.queryByRole('button', { name: 'Mark all as read' }),
        ).not.toBeInTheDocument();
    },
};

/** A guest: no bell at all. */
export const Guest: Story = {
    args: { whatsNew: null },
    play: async ({ canvas }) => {
        await expect(
            canvas.queryByRole('button', { name: bell }),
        ).not.toBeInTheDocument();
    },
};
