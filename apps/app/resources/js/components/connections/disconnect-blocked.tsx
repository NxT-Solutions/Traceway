import { Link } from '@inertiajs/react';
import { edit as editSecurity } from '@/routes/security';

type Props = {
    /** The id the Disconnect button points to for its description. */
    id: string;
    message: string;
    hasPassword?: boolean;
    className?: string;
};

/**
 * Why a service cannot be disconnected yet, such as it being the rider's last way to log in, and where to add
 * another way first.
 */
export default function DisconnectBlocked({
    id,
    message,
    hasPassword = false,
    className,
}: Props) {
    return (
        <p
            className={className ? `cx-blocked ${className}` : 'cx-blocked'}
            id={id}
        >
            {message}{' '}
            <Link className="cx-link" href={editSecurity()}>
                {hasPassword
                    ? 'Add a passkey in Security'
                    : 'Set a password or passkey in Security'}
            </Link>
        </p>
    );
}
