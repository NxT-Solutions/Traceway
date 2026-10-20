import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, waitFor } from 'storybook/test';
import ReleasesController from '@/actions/App/Http/Controllers/Settings/ReleasesController';
import WhatsNew from '@/pages/settings/whats-new';
import type { ReleaseNotes } from '@/types/releases';
import { expectToast } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    pathOf,
    redirect,
} from '../../support/server';

type Props = ComponentProps<typeof WhatsNew>;

/**
 * Settings, What's new (`/settings/whats-new`): the rider notes of each release since the rider joined, newest
 * first. The server renders the notes from Markdown, with headings from h3.
 *
 * The flow stories mark notes read against a mocked server (Mock Service Worker) that answers as ReleasesController
 * does: a POST that goes `back()`, answered as a partial reload of `whatsNew` and `releases` only; Mark all as read
 * also flashes "You're up to date.".
 */
const meta = {
    title: "Pages/Settings/What's new",
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const v0111: ReleaseNotes = {
    id: 42,
    tag: 'v0.11.1',
    name: 'A clearer note when Traceway updates',
    summary:
        'The note that a new version is out is easier to read, with what changed and a button to read more.',
    published_at: '2026-10-17T09:00:00Z',
    read: false,
    html: '<h3>Fixes</h3><ul><li><strong>A clearer note when Traceway updates.</strong> The note that a new version is out now shows the version and what changed, with a button to read more.</li></ul>',
};

const v0110: ReleaseNotes = {
    id: 41,
    tag: 'v0.11.0',
    name: 'Log in with the accounts you already have',
    summary:
        'Log in with Google, GitHub, Discord, Wahoo and more, see your connections in Settings, and confirm a new email before it counts.',
    published_at: '2026-10-14T15:30:00Z',
    read: true,
    html: '<h3>New</h3><ul><li><strong>Log in with an account you already have.</strong> On the log in page, you can use an account such as Google or GitHub instead of a password, and the page marks the one you used last time on this browser.</li><li><strong>Your connections in one place.</strong> Settings, then Connections, shows each account you connected and what Traceway keeps from it, and lets you disconnect it.</li></ul><h3>Fixes</h3><ul><li><strong>A new password logs you out everywhere.</strong> When you reset a forgotten password, every phone and computer that was logged in to your account has to log in again.</li></ul>',
};

/** The shared props with the bell's unread releases, as ReleaseNotesQuery::summaryFor() sends them. */
function shared(unread: ReleaseNotes[]) {
    return {
        ...sharedProps(),
        whatsNew: {
            unread: unread.length,
            items: unread.map((release) => ({
                id: release.id,
                tag: release.tag,
                name: release.name,
                published_at: release.published_at,
                headline: release.summary,
            })),
        },
    };
}

function page(props: Props, unread: ReleaseNotes[]) {
    return () => (
        <InertiaPage
            name="settings/whats-new"
            component={WhatsNew}
            props={props}
            shared={shared(unread)}
            url={ReleasesController.index.url()}
        />
    );
}

/** One release the rider has not read yet, so the nav shows a count. */
export const Unread: Story = {
    render: page({ releases: [v0111, v0110] }, [v0111]),
};

export const AllRead: Story = {
    render: page({ releases: [{ ...v0111, read: true }, v0110] }, []),
};

/** A rider who joined after the last release. */
export const NothingYet: Story = { render: page({ releases: [] }, []) };

/** What the mocked server marked read. */
const readRequest = fn();

/** The page after the read, everything read, answered as the partial reload asked. */
function everythingRead(toast?: string) {
    return http.get(ReleasesController.index.url(), ({ request }) =>
        inertiaPage(
            'settings/whats-new',
            { releases: [{ ...v0111, read: true }, v0110] },
            {
                url: ReleasesController.index.url(),
                shared: shared([]),
                flash: toast
                    ? { toast: { type: 'success', message: toast } }
                    : {},
                request,
            },
        ),
    );
}

/** Sam marks v0.11.1 read: its button and New go, and focus moves to its title. */
export const MarksOneRead: Story = {
    render: Unread.render,
    beforeEach({ msw }) {
        readRequest.mockClear();
        msw.use(
            http.post(pathOf(ReleasesController.read), async ({ params }) => {
                readRequest(params.release);
                await network();

                return redirect(ReleasesController.index.url());
            }),
            everythingRead(),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Mark v0.11.1 as read', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Mark as read v0.11.1' }),
            );
        });

        await step('It reads as read, and its title has focus', async () => {
            await waitFor(() =>
                expect(
                    canvas.queryByRole('button', {
                        name: 'Mark as read v0.11.1',
                    }),
                ).toBeNull(),
            );
            await expect(
                canvas.queryByText('New', { selector: '.release-new' }),
            ).toBeNull();
            await waitFor(() =>
                expect(
                    canvas.getByRole('heading', {
                        name: 'A clearer note when Traceway updates',
                    }),
                ).toHaveFocus(),
            );
            await expect(readRequest).toHaveBeenCalledWith('42');
        });
    },
};

/** Sam marks everything read at once, and a toast says they are up to date. */
export const MarksAllRead: Story = {
    render: Unread.render,
    beforeEach({ msw }) {
        readRequest.mockClear();
        msw.use(
            http.post(ReleasesController.readAll.url(), async () => {
                readRequest('all');
                await network();

                return redirect(ReleasesController.index.url());
            }),
            everythingRead("You're up to date."),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Mark all as read', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Mark all as read' }),
            );
        });

        await step('Nothing is left to read', async () => {
            await expectToast(canvas, "You're up to date.");
            await expect(
                canvas.queryByRole('button', { name: 'Mark all as read' }),
            ).toBeNull();
            await expect(readRequest).toHaveBeenCalledWith('all');
        });
    },
};
