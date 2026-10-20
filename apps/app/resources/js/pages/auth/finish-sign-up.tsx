import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConnectionMark } from '@/components/connection-mark';
import InputError from '@/components/input-error';
import SignInLegal from '@/components/sign-in-legal';
import Turnstile from '@/components/turnstile';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cancel, store } from '@/routes/connections/finish';
import type { ConnectionSlug } from '@/types';

type Props = {
    service: { slug: ConnectionSlug; label: string };
    /** What the service gave, or an empty string. */
    name: string;
    email: string;
    /** The public key for Cloudflare's person check, or null when it is off. */
    turnstileSiteKey: string | null;
};

/**
 * The last step for a new rider whose service gave no email, or one it did not confirm. Traceway emails a link,
 * and the account opens when the rider uses it. Leaving the page puts it out of the browser's history, so Back
 * does not bring back a form that already sent its link.
 */
export default function FinishSignUp({
    service,
    name,
    email,
    turnstileSiteKey,
}: Props) {
    const [cancelling, setCancelling] = useState(false);

    function cancelSignUp(): void {
        router.delete(cancel.url(), {
            replace: true,
            onStart: () => setCancelling(true),
            onFinish: () => setCancelling(false),
        });
    }

    return (
        <>
            <Head title="Finish your account" />

            <p className="via">
                <ConnectionMark slug={service.slug} size={20} />
                <span>Signing up with {service.label}</span>
            </p>

            <Form
                {...store.form()}
                options={{ replace: true }}
                className="fields"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="field">
                            <div className="lab">
                                <label htmlFor="name">Name</label>
                            </div>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                required
                                autoFocus={name === ''}
                                autoComplete="name"
                                defaultValue={name}
                                placeholder="Full name"
                                aria-invalid={errors.name ? true : undefined}
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
                            />
                            <InputError
                                id="name-error"
                                role="alert"
                                message={errors.name}
                                className="err"
                            />
                        </div>

                        <div className="field">
                            <div className="lab">
                                <label htmlFor="email">Email address</label>
                            </div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus={name !== '' && email === ''}
                                autoComplete="email"
                                defaultValue={email}
                                placeholder="email@example.com"
                                aria-invalid={errors.email ? true : undefined}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />
                            <InputError
                                id="email-error"
                                role="alert"
                                message={errors.email}
                                className="err"
                            />
                        </div>

                        <SignInLegal intent="create" />

                        <Turnstile
                            siteKey={turnstileSiteKey}
                            error={errors.turnstile}
                            attempt={errors}
                        />

                        <button
                            type="submit"
                            className="fill big"
                            disabled={processing || cancelling}
                            data-test="finish-sign-up-button"
                        >
                            {processing ? (
                                <Spinner
                                    aria-hidden="true"
                                    className="motion-reduce:animate-none"
                                />
                            ) : null}
                            Email me a link
                        </button>

                        <button
                            type="button"
                            className="text-btn"
                            disabled={processing || cancelling}
                            onClick={cancelSignUp}
                        >
                            Cancel
                            <span className="vh">
                                {' '}
                                signing up with {service.label}
                            </span>
                        </button>
                    </>
                )}
            </Form>
        </>
    );
}

FinishSignUp.layout = ({ service, email }: Props) => ({
    title: 'Finish your account',
    description:
        email === ''
            ? `${service.label} does not share an email. Add yours, and Traceway emails you a link to finish your account.`
            : `${service.label} shared this email. Check it, and Traceway emails you a link to finish your account.`,
});
