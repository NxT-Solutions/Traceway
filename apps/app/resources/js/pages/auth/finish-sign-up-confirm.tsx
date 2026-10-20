import { Form, Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { ConnectionMark } from '@/components/connection-mark';
import SignInLegal from '@/components/sign-in-legal';
import { Spinner } from '@/components/ui/spinner';
import { open as openAccount } from '@/routes/connections/finish';
import type { ConnectionSlug } from '@/types';

type Props = {
    service: { slug: ConnectionSlug; label: string };
    name: string;
    email: string;
    /** From the link in the email. The button sends it back. */
    token: string;
    /** False when the link was opened in another browser than the one that asked for it. */
    connects: boolean;
};

/**
 * Where the link in the email lands. Opening the page changes nothing, so a mail scanner that opens the link first
 * does not use it up. The button opens the account with the email confirmed. Only the browser that asked for the
 * link connects the service, and the page says so in any other.
 */
export default function FinishSignUpConfirm({
    service,
    name,
    email,
    token,
    connects,
}: Props) {
    return (
        <>
            <Head title="Create your account" />

            {connects ? (
                <p className="via">
                    <ConnectionMark slug={service.slug} size={20} />
                    <span>Signing up with {service.label}</span>
                </p>
            ) : (
                <div className="notice">
                    <Info size={20} aria-hidden="true" />
                    <p>
                        This is not the browser where you started, so{' '}
                        {service.label} is not connected now. You can connect it
                        later in Settings, under Connections.
                    </p>
                </div>
            )}

            <Form {...openAccount.form(token)} className="fields">
                {({ processing }) => (
                    <>
                        <dl className="fields">
                            <div className="field">
                                <dt className="lab">Name</dt>
                                <dd>{name}</dd>
                            </div>
                            <div className="field">
                                <dt className="lab">Email address</dt>
                                <dd>{email}</dd>
                            </div>
                        </dl>

                        <SignInLegal intent="create" />

                        <button
                            type="submit"
                            className="fill big"
                            disabled={processing}
                            data-test="finish-sign-up-confirm-button"
                        >
                            {processing ? (
                                <Spinner
                                    aria-hidden="true"
                                    className="motion-reduce:animate-none"
                                />
                            ) : null}
                            Create account
                        </button>
                    </>
                )}
            </Form>
        </>
    );
}

FinishSignUpConfirm.layout = ({ service, connects }: Props) => ({
    title: 'Create your account',
    description: connects
        ? `Create your account, then you log in with ${service.label}. You can change your name later in Settings.`
        : 'Create your account. You can change your name later in Settings.',
});
