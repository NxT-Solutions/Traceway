import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConnectionMark } from '@/components/connection-mark';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store } from '@/routes/connections/pending';
import type { ConnectionSlug } from '@/types';

type Props = {
    service: { slug: ConnectionSlug; label: string };
    /** How the service names the account, when Traceway may keep that. */
    account: string | null;
    /** The Traceway account the rider is logged in to. */
    email: string;
};

/**
 * After logging in, the rider says yes to connecting the service they came from. It names both accounts, so a
 * service someone else left waiting on a shared computer is easy to turn down.
 */
export default function ConnectPending({ service, account, email }: Props) {
    const [declining, setDeclining] = useState(false);

    function decline(): void {
        router.delete(destroy.url(), {
            onStart: () => setDeclining(true),
            onFinish: () => setDeclining(false),
        });
    }

    return (
        <>
            <Head title={`Connect ${service.label}`} />

            <p className="via">
                <ConnectionMark slug={service.slug} size={20} />
                <span>
                    {service.label}
                    {account !== null ? (
                        <>
                            {' '}
                            account <strong>{account}</strong>
                        </>
                    ) : null}
                </span>
            </p>

            <p className="legal">
                It connects to your Traceway account <strong>{email}</strong>.
                If this is not your {service.label} account, choose Not now.
            </p>

            <Form {...store.form()} className="fields">
                {({ processing }) => (
                    <>
                        <button
                            type="submit"
                            className="fill big"
                            disabled={processing || declining}
                            data-test="connect-pending-button"
                        >
                            {processing ? (
                                <Spinner
                                    aria-hidden="true"
                                    className="motion-reduce:animate-none"
                                />
                            ) : null}
                            Connect {service.label}
                        </button>

                        <button
                            type="button"
                            className="text-btn"
                            disabled={processing || declining}
                            onClick={decline}
                        >
                            Not now
                            <span className="vh">
                                , do not connect {service.label}
                            </span>
                        </button>
                    </>
                )}
            </Form>
        </>
    );
}

ConnectPending.layout = ({ service }: Props) => ({
    title: `Connect ${service.label}?`,
    description: `Then you can log in with ${service.label} too.`,
});
