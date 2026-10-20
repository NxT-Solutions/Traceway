import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, waitFor } from 'storybook/test';
import Profile from '@/pages/settings/profile';
import { edit, update } from '@/routes/profile';
import { cancel } from '@/routes/profile/email';
import { rider } from '../../support/fixtures';
import { expectToast } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    form,
    http,
    inertiaPage,
    network,
    redirect,
    sentData,
    validationErrors,
} from '../../support/server';

type Props = ComponentProps<typeof Profile>;

/**
 * Settings, Profile (`/settings/profile`): the rider's name and the email they log in with, then Delete account.
 * A new email counts once the rider opens the link sent to it; changing it asks for the password first.
 *
 * The flow stories save the form to a mocked server (Mock Service Worker) that answers as ProfileController and
 * ProfileUpdateRequest do: a PATCH that is a 303 back to `/settings/profile` with a success toast, or with the
 * `current_password` error when a new email comes without the password. "Keep" on a waiting email is a DELETE
 * that answers the same way.
 */
const meta = {
    title: 'Pages/Settings/Profile',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const props: Props = {
    mustVerifyEmail: true,
    hasPassword: true,
    confirmedRecently: false,
    pendingEmail: null,
};

function page(overrides: Partial<Props> = {}, shared = sharedProps()) {
    return () => (
        <InertiaPage
            name="settings/profile"
            component={Profile}
            props={{ ...props, ...overrides }}
            shared={shared}
            url={edit.url()}
        />
    );
}

const unverified = sharedProps({
    user: { ...rider, email_verified_at: null },
    can: null,
});

export const Verified: Story = { render: page() };

/** The rider has not opened the confirmation link yet. */
export const UnverifiedEmail: Story = { render: page({}, unverified) };

/** They asked for the link again. */
export const VerificationLinkSent: Story = {
    render: page({ status: 'verification-link-sent' }, unverified),
};

/** A new email waits for its link. Until then the rider logs in with the old one. */
export const PendingEmail: Story = {
    render: page({ pendingEmail: 'sam.rivera@example.org' }),
};

/** Opened with a connected service and never set a password: deleting asks them to confirm another way. */
export const NoPassword: Story = { render: page({ hasPassword: false }) };

/** What the mocked server got from the form. */
const saveRequest = fn();

/** ProfileController@update: records what it got, then sends the rider back to the page. */
const saveRoute = form(update.form(), async ({ request }) => {
    saveRequest(await sentData(request));
    await network();

    return redirect(edit.url(), 303);
});

/** The page after a save, with the rider and props as the server now has them, and its toast. */
function savedPage(
    overrides: Partial<Props>,
    user: Partial<typeof rider>,
    toast: string,
) {
    return http.get(edit.url(), () =>
        inertiaPage(
            'settings/profile',
            { ...props, ...overrides },
            {
                url: edit.url(),
                shared: sharedProps({ user: { ...rider, ...user }, can: null }),
                flash: { toast: { type: 'success', message: toast } },
            },
        ),
    );
}

/** Sam changes the name and saves: the toast says so, and the new name stays in the field. */
export const SavesName: Story = {
    render: page(),
    beforeEach({ msw }) {
        saveRequest.mockClear();
        msw.use(
            saveRoute,
            savedPage({}, { name: 'Samantha Rivera' }, 'Profile updated.'),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Change the name and save', async () => {
            const name = canvas.getByLabelText('Name');

            await userEvent.clear(name);
            await userEvent.type(name, 'Samantha Rivera');
            await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        });

        await step('A toast says the profile is saved', async () => {
            await expectToast(canvas, 'Profile updated.');
            await expect(canvas.getByLabelText('Name')).toHaveValue(
                'Samantha Rivera',
            );
            await expect(saveRequest).toHaveBeenCalledWith({
                name: 'Samantha Rivera',
                email: 'sam@example.com',
            });
        });
    },
};

/** A new email without the password: ProfileUpdateRequest sends the form back with the error under the password. */
export const NewEmailNeedsPassword: Story = {
    render: page(),
    beforeEach({ msw }) {
        saveRequest.mockClear();
        msw.use(
            saveRoute,
            http.get(edit.url(), () =>
                validationErrors(
                    'settings/profile',
                    props,
                    {
                        current_password:
                            'Enter your password to change your email.',
                    },
                    { url: edit.url() },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type a new email', async () => {
            const email = canvas.getByLabelText('Email address');

            await userEvent.clear(email);
            await userEvent.type(email, 'sam.rivera@example.org');
        });

        await step('The page asks for the password', async () => {
            await expect(
                canvas.getByLabelText('Current password'),
            ).toBeVisible();
        });

        await step('Save without it', async () => {
            await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        });

        await step('The error shows under the password', async () => {
            await expect(
                await canvas.findByText(
                    'Enter your password to change your email.',
                ),
            ).toBeVisible();
        });
    },
};

/**
 * A new email with the password: the toast says to open the link, and the page says the new email waits for it.
 * Sam still logs in with the old one until then.
 */
export const ChangesEmail: Story = {
    render: page(),
    beforeEach({ msw }) {
        saveRequest.mockClear();
        msw.use(
            saveRoute,
            savedPage(
                { pendingEmail: 'sam.rivera@example.org' },
                {},
                'Check sam.rivera@example.org. Your account signs in with it once you open the link sent there.',
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type a new email and the password', async () => {
            const email = canvas.getByLabelText('Email address');

            await userEvent.clear(email);
            await userEvent.type(email, 'sam.rivera@example.org');
            await userEvent.type(
                canvas.getByLabelText('Current password'),
                'correct horse battery',
            );
            await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        });

        await step('A toast says to open the link', async () => {
            await expectToast(
                canvas,
                'Check sam.rivera@example.org. Your account signs in with it once you open the link sent there.',
            );
            await expect(saveRequest).toHaveBeenCalledWith({
                name: 'Sam Rivera',
                email: 'sam.rivera@example.org',
                current_password: 'correct horse battery',
            });
        });

        await step('The page says the new email waits', async () => {
            await expect(canvas.getByRole('status')).toHaveTextContent(
                'Waiting for you to confirm sam.rivera@example.org',
            );
            // The field shows the email Sam logs in with until then.
            await expect(canvas.getByLabelText('Email address')).toHaveValue(
                'sam@example.com',
            );
        });
    },
};

/** A new email waits, and Sam keeps the old one: the note goes, and the email field takes focus. */
export const KeepsOldEmail: Story = {
    render: page({ pendingEmail: 'sam.rivera@example.org' }),
    beforeEach({ msw }) {
        msw.use(
            http.delete(cancel.url(), async () => {
                await network();

                return redirect(edit.url(), 303);
            }),
            savedPage({}, {}, 'Your email stays as it is.'),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Keep sam@example.com', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Keep sam@example.com' }),
            );
        });

        await step('The note goes, and a toast says so', async () => {
            await expectToast(canvas, 'Your email stays as it is.');
            await expect(
                canvas.queryByText(/Waiting for you to confirm/),
            ).toBeNull();
            await waitFor(() =>
                expect(canvas.getByLabelText('Email address')).toHaveFocus(),
            );
        });
    },
};
