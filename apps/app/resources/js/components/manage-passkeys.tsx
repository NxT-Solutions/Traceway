import { Link, router, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import { edit as editConnections } from '@/routes/connections';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
    /** True when the rider's one passkey is their only way to log in: no password and no connected account. */
    passkeyIsLastWayIn?: boolean;
    /** The id of the password card on the same page. */
    passwordCard?: string;
};

const lastWayInId = 'passkey-last-way-in';

const EmptyState = () => {
    return (
        <div className="p-8 text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                <KeyRound className="h-7 w-7 text-muted-foreground" />
            </div>
            <p className="font-medium">No passkeys yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
                Add a passkey to sign in without a password
            </p>
        </div>
    );
};

export default function ManagePasskeys(props: Props) {
    const passkeys = props.passkeys ?? [];
    const lastWayIn =
        (props.passkeyIsLastWayIn ?? false) && passkeys.length === 1;
    const refused = usePage().props.errors.passkey;

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <div className="card">
            <Heading
                variant="small"
                title="Passkeys"
                description="Manage your passkeys for passwordless sign-in"
            />

            <div className="overflow-hidden rounded-lg border border-border">
                {passkeys.length > 0 ? (
                    passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={handleDelete}
                            removeBlockedBy={
                                lastWayIn ? lastWayInId : undefined
                            }
                        />
                    ))
                ) : (
                    <EmptyState />
                )}
            </div>

            {lastWayIn ? (
                <p className="cx-blocked" id={lastWayInId}>
                    This passkey is your only way to log in.{' '}
                    {props.passwordCard === undefined ? (
                        'Set a password'
                    ) : (
                        <a className="cx-link" href={`#${props.passwordCard}`}>
                            Set a password
                        </a>
                    )}{' '}
                    or{' '}
                    <Link className="cx-link" href={editConnections()}>
                        connect an account
                    </Link>{' '}
                    first.
                </p>
            ) : null}

            <InputError message={refused} role="alert" />

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </div>
    );
}
