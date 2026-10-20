import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, screen, waitFor, within } from 'storybook/test';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { destroy as removePasskey } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Security from '@/pages/settings/security';
import {
    confirm as confirmTwoFactor,
    enable,
    qrCode,
    recoveryCodes,
    secretKey,
} from '@/routes/two-factor';
import type { Passkey } from '@/types/auth';
import { rider } from '../../support/fixtures';
import { expectToast, serverTime } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    form,
    HttpResponse,
    http,
    inertiaPage,
    network,
    pathOf,
    redirect,
    sentData,
    validationErrors,
} from '../../support/server';

type Props = ComponentProps<typeof Security>;

/**
 * Settings, Security (`/settings/security`): the password, two-factor sign-in, and passkeys.
 *
 * The flow stories run against a mocked server (Mock Service Worker) that answers as SecurityController and Fortify
 * do. Saving the password is a PUT that is a 303 back with the "Password updated." toast, or with
 * `current_password` wrong. Enable 2FA is a POST that goes back to the page, then the setup modal fetches the QR
 * code and setup key as JSON and confirms the code, which comes back wrong in the `confirmTwoFactorAuthentication`
 * bag or turns two-factor on. Removing a passkey is a DELETE that goes back to the page without it. Adding a passkey
 * needs the browser's WebAuthn prompt, so no story runs it.
 */
const meta = {
    title: 'Pages/Settings/Security',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

const props: Props = {
    passwordRules:
        'minlength: 12; required: lower; required: upper; required: digit; required: special;',
    hasPassword: true,
    canManageTwoFactor: true,
    requiresConfirmation: true,
    twoFactorEnabled: false,
    canManagePasskeys: true,
    passkeys: [],
    passkeyIsLastWayIn: false,
};

const twoPasskeys: Passkey[] = [
    {
        id: 2,
        name: 'MacBook Air',
        authenticator: 'iCloud Keychain',
        created_at_diff: '3 weeks ago',
        last_used_at_diff: '2 days ago',
    },
    {
        id: 1,
        name: 'Pixel 8',
        authenticator: 'Google Password Manager',
        created_at_diff: '1 month ago',
        last_used_at_diff: null,
    },
];

const twoFactorOn = sharedProps({
    user: { ...rider, two_factor_enabled: true },
    can: null,
});

function page(overrides: Partial<Props> = {}, shared = sharedProps()) {
    return () => (
        <InertiaPage
            name="settings/security"
            component={Security}
            props={{ ...props, ...overrides }}
            shared={shared}
            url="/settings/security"
        />
    );
}

/** Fortify's ten recovery codes, made up. */
const codes = [
    'Xk3pQ9mWzR-7bTn2LvYcH',
    'Pd8sJ4fNqA-2gKw6RmZeU',
    'Lr5tB1yHcX-9vMj3QnWpD',
    'Fz7wK2pLsE-4hNq8TbRyV',
    'Mg6cV9dRtJ-1xPk5ZsHwA',
    'Hy2nT8qWbK-6fLr3MvCzS',
    'Qe4jX7mPgN-8dWt1KyBsF',
    'Bw9rD3vZhL-5cJn7PxTmQ',
];

/** What Fortify's recovery codes route answers: the codes as JSON. */
const recoveryCodesRoute = http.get(recoveryCodes.url(), () =>
    HttpResponse.json(codes),
);

/** A password, no second step, no passkeys. */
export const TwoFactorOff: Story = { render: page() };

/** Two-factor sign-in on, and two passkeys. The recovery codes come from the mocked server. */
export const TwoFactorOn: Story = {
    render: page(
        { twoFactorEnabled: true, passkeys: twoPasskeys },
        twoFactorOn,
    ),
    beforeEach({ msw }) {
        msw.use(recoveryCodesRoute);
    },
};

/** No password and one passkey, the only way in: the passkey cannot be removed until a password is set. */
export const PasskeyIsLastWayIn: Story = {
    render: page({
        hasPassword: false,
        passkeys: [
            {
                id: 3,
                name: 'iPhone 15',
                authenticator: 'iCloud Keychain',
                created_at_diff: '5 days ago',
                last_used_at_diff: '1 hour ago',
            },
        ],
        passkeyIsLastWayIn: true,
    }),
};

/** What the mocked server got from the password form. */
const passwordRequest = fn();

const passwordRoute = form(
    SecurityController.update.form(),
    async ({ request }) => {
        passwordRequest(await sentData(request));
        await network();

        return redirect(SecurityController.edit.url(), 303);
    },
);

/** The current password is wrong: the error shows under it, the fields clear, and it takes focus. */
export const WrongCurrentPassword: Story = {
    render: page(),
    beforeEach({ msw }) {
        passwordRequest.mockClear();
        msw.use(
            passwordRoute,
            http.get(SecurityController.edit.url(), () =>
                validationErrors(
                    'settings/security',
                    props,
                    { current_password: 'The password is incorrect.' },
                    { url: SecurityController.edit.url() },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type a wrong current password and a new one', async () => {
            await userEvent.type(
                canvas.getByLabelText('Current password'),
                'not-my-password',
            );
            await userEvent.type(
                canvas.getByLabelText('New password'),
                'Gravel-loop-2026',
            );
            await userEvent.type(
                canvas.getByLabelText('Confirm password'),
                'Gravel-loop-2026',
            );
            await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        });

        await step('The error shows, and the field takes focus', async () => {
            await expect(
                await canvas.findByText('The password is incorrect.'),
            ).toBeVisible();
            await waitFor(() =>
                expect(canvas.getByLabelText('Current password')).toHaveFocus(),
            );
            await expect(canvas.getByLabelText('New password')).toHaveValue('');
        });
    },
};

/** The new password is saved: a toast says so, and the fields clear. */
export const ChangesPassword: Story = {
    render: page(),
    beforeEach({ msw }) {
        passwordRequest.mockClear();
        msw.use(
            passwordRoute,
            http.get(SecurityController.edit.url(), () =>
                inertiaPage('settings/security', props, {
                    url: SecurityController.edit.url(),
                    flash: {
                        toast: {
                            type: 'success',
                            message: 'Password updated.',
                        },
                    },
                }),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Type the current password and a new one', async () => {
            await userEvent.type(
                canvas.getByLabelText('Current password'),
                'correct horse battery',
            );
            await userEvent.type(
                canvas.getByLabelText('New password'),
                'Gravel-loop-2026',
            );
            await userEvent.type(
                canvas.getByLabelText('Confirm password'),
                'Gravel-loop-2026',
            );
            await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        });

        await step(
            'A toast says it is saved, and the fields clear',
            async () => {
                await expectToast(canvas, 'Password updated.');
                await expect(canvas.getByLabelText('New password')).toHaveValue(
                    '',
                );
                await expect(passwordRequest).toHaveBeenCalledWith({
                    current_password: 'correct horse battery',
                    password: 'Gravel-loop-2026',
                    password_confirmation: 'Gravel-loop-2026',
                });
            },
        );
    },
};

/** A made-up QR code, as Fortify's QR route sends it: an SVG in JSON. */
const qrSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21"><path fill="#000" d="M0 0h7v7H0zM14 0h7v7h-7zM0 14h7v7H0zM9 2h2v2H9zM9 9h3v3H9zM14 9h2v5h-2zM10 15h4v2h-4zM17 16h2v3h-2z"/></svg>`;

/** What the confirm route got. */
const confirmRequest = fn();

/**
 * Sam turns two-factor sign-in on. Enable 2FA opens the setup modal, which fetches the QR code and the setup key;
 * Continue asks for the code; a wrong code shows Fortify's error, and the right one closes the modal with two-factor
 * on and the recovery codes ready.
 */
export const EnablesTwoFactor: Story = {
    render: page(),
    beforeEach({ msw }) {
        confirmRequest.mockClear();
        let confirmed = false;

        msw.use(
            http.post(enable.url(), async () => {
                await network();

                return redirect(SecurityController.edit.url());
            }),
            http.get(qrCode.url(), () =>
                HttpResponse.json({
                    svg: qrSvg,
                    url: 'otpauth://totp/Traceway:sam%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Traceway',
                }),
            ),
            http.get(secretKey.url(), () =>
                HttpResponse.json({ secretKey: 'JBSWY3DPEHPK3PXP' }),
            ),
            http.post(confirmTwoFactor.url(), async ({ request }) => {
                const sent = await sentData(request);

                confirmRequest(sent);
                confirmed = sent.code === '904615';
                await network();

                return redirect(SecurityController.edit.url());
            }),
            http.get(SecurityController.edit.url(), () =>
                confirmed
                    ? inertiaPage(
                          'settings/security',
                          { ...props, twoFactorEnabled: true },
                          {
                              url: SecurityController.edit.url(),
                              shared: twoFactorOn,
                          },
                      )
                    : confirmRequest.mock.calls.length > 0
                      ? validationErrors(
                            'settings/security',
                            props,
                            {
                                confirmTwoFactorAuthentication: {
                                    code: 'The provided two factor authentication code was invalid.',
                                },
                            },
                            { url: SecurityController.edit.url() },
                        )
                      : inertiaPage('settings/security', props, {
                            url: SecurityController.edit.url(),
                        }),
            ),
            recoveryCodesRoute,
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Enable 2FA', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Enable 2FA' }),
            );
        });

        const modal = within(await screen.findByRole('dialog'));

        await step(
            'The modal shows the QR code and the setup key',
            async () => {
                // The dialog fades in.
                await waitFor(() =>
                    expect(
                        modal.getByRole('heading', {
                            name: 'Enable two-factor authentication',
                        }),
                    ).toBeVisible(),
                );
                await expect(
                    await modal.findByRole('textbox', { name: 'Setup key' }),
                ).toHaveValue('JBSWY3DPEHPK3PXP');
                await expect(
                    modal.getByRole('button', { name: 'Copy setup key' }),
                ).toBeVisible();
                await userEvent.click(
                    modal.getByRole('button', { name: 'Continue' }),
                );
            },
        );

        await step('An old code is turned down', async () => {
            await expect(
                await modal.findByRole('heading', {
                    name: 'Verify authentication code',
                }),
            ).toBeVisible();
            await userEvent.keyboard('418207');
            await userEvent.click(
                modal.getByRole('button', { name: 'Confirm' }),
            );
            await expect(
                await modal.findByText(
                    'The provided two factor authentication code was invalid.',
                ),
            ).toBeVisible();
        });

        await step('The code from the app turns it on', async () => {
            const code = modal.getByRole('textbox');

            await userEvent.clear(code);
            await userEvent.type(code, '904615');
            await userEvent.click(
                modal.getByRole('button', { name: 'Confirm' }),
            );
            await waitFor(
                () => expect(screen.queryByRole('dialog')).toBeNull(),
                serverTime,
            );
            await expect(
                await canvas.findByRole('button', { name: 'Disable 2FA' }),
            ).toBeVisible();
            await expect(confirmRequest).toHaveBeenLastCalledWith({
                code: '904615',
            });
        });

        await step('The recovery codes are ready to view', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'View recovery codes' }),
            );
            const firstCode = await canvas.findByText(codes[0] ?? '');

            // The list opens with a short animation.
            await waitFor(() => expect(firstCode).toBeVisible());
        });
    },
};

/** Which passkey the mocked server removed. */
const removeRequest = fn();

/** Sam removes the Pixel 8 passkey after saying yes in the dialog, and the list keeps the MacBook Air. */
export const RemovesPasskey: Story = {
    render: page(
        { twoFactorEnabled: true, passkeys: twoPasskeys },
        twoFactorOn,
    ),
    beforeEach({ msw }) {
        removeRequest.mockClear();
        msw.use(
            recoveryCodesRoute,
            http.delete(pathOf(removePasskey), async ({ params }) => {
                removeRequest(params.passkey);
                await network();

                return redirect(SecurityController.edit.url(), 303);
            }),
            http.get(SecurityController.edit.url(), () =>
                inertiaPage(
                    'settings/security',
                    {
                        ...props,
                        twoFactorEnabled: true,
                        passkeys: twoPasskeys.filter(
                            (passkey) => passkey.id !== 1,
                        ),
                    },
                    { url: SecurityController.edit.url(), shared: twoFactorOn },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Ask to remove the Pixel 8 passkey', async () => {
            await userEvent.click(
                canvas.getByRole('button', {
                    name: 'Remove passkey Pixel 8',
                }),
            );
        });

        await step('Say yes in the dialog', async () => {
            const dialog = within(await screen.findByRole('dialog'));

            // The dialog fades in.
            await waitFor(() =>
                expect(
                    dialog.getByText(/remove the "Pixel 8" passkey/),
                ).toBeVisible(),
            );
            await userEvent.click(
                dialog.getByRole('button', { name: 'Remove passkey' }),
            );
        });

        await step('The list keeps the MacBook Air only', async () => {
            await waitFor(
                () => expect(canvas.queryByText('Pixel 8')).toBeNull(),
                serverTime,
            );
            await expect(canvas.getByText('MacBook Air')).toBeVisible();
            await expect(removeRequest).toHaveBeenCalledWith('1');
        });
    },
};
