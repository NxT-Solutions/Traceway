import { Form, Head, usePage } from '@inertiajs/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import SignInAlert from '@/components/sign-in-alert';
import SignInLink from '@/components/sign-in-link';
import { namesItsAction } from '@/components/sign-in-tile';
import { connection as confirmWith, store } from '@/routes/password/confirm';
import type { ConnectionSlug } from '@/types';

type Props = {
    hasPassword: boolean;
    /** The connected services the rider can log in with, to confirm it is them. */
    connections: { slug: ConnectionSlug; label: string }[];
};

export default function ConfirmPassword({ hasPassword, connections }: Props) {
    const connectionError = usePage().props.errors.connection;
    // Only a rider without a password gets the services, so the password and the services never share the page.
    const hasConnections = connections.length > 0;

    return (
        <>
            <Head
                title={hasPassword ? 'Confirm password' : 'Confirm it is you'}
            />

            {/* Why the service did not confirm it is the rider takes focus before the password field. */}
            <SignInAlert message={connectionError} focus />

            <PasskeyVerify
                variant="paper"
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirm with passkey"
                loadingLabel="Confirming…"
                separator={hasPassword ? 'or confirm with password' : false}
            />

            {hasConnections ? (
                <ul className="providers single">
                    {connections.map((service) => (
                        <li key={service.slug}>
                            <SignInLink
                                slug={service.slug}
                                href={confirmWith.url(service.slug)}
                            >
                                {namesItsAction(service.slug) ? (
                                    <span>
                                        Continue with {service.label}
                                        <span className="vh">
                                            , to confirm it is you
                                        </span>
                                    </span>
                                ) : (
                                    <span>Confirm with {service.label}</span>
                                )}
                            </SignInLink>
                        </li>
                    ))}
                </ul>
            ) : null}

            {hasPassword ? (
                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="fields"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="field">
                                <div className="lab">
                                    <label htmlFor="password">Password</label>
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    placeholder="Password"
                                    autoComplete="current-password"
                                    autoFocus={!connectionError}
                                />
                                <InputError
                                    message={errors.password}
                                    className="err"
                                />
                            </div>

                            <button
                                type="submit"
                                className="fill big"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing
                                    ? 'Confirming…'
                                    : 'Confirm password'}
                            </button>
                        </>
                    )}
                </Form>
            ) : null}

            {!hasPassword && !hasConnections ? (
                <p className="signup">
                    To set a password instead, log out, then choose “Forgot your
                    password?” on the log in page.
                </p>
            ) : null}
        </>
    );
}

ConfirmPassword.layout = ({ hasPassword }: Props) => ({
    title: hasPassword ? 'Confirm password' : 'Confirm it is you',
    description: hasPassword
        ? 'This is a secure area of the application. Please confirm your password before continuing.'
        : 'Before you go on, show it is you.',
});
