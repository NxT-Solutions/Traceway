import { Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { send } from '@/routes/verification';

type Props = {
    /** The id Connect and Disconnect point to for why they are turned off. */
    id: string;
    /** True right after the rider asked for the email again. */
    sent: boolean;
};

/**
 * Adding or removing a connection waits until the rider confirms their email. This says so once, with a way to
 * get the email again, instead of buttons that end on the confirm page. The button keeps focus after it sends,
 * and the note beside it says the email is on its way.
 */
export default function ConfirmEmailFirst({ id, sent }: Props) {
    return (
        <div className="cx-confirm">
            <Mail size={16} aria-hidden="true" />
            <div className="cx-confirm-body">
                <p id={id}>
                    Confirm your email first. Then you can add or remove a
                    connection.
                </p>
                <div className="cx-confirm-row">
                    <Link
                        href={send()}
                        as="button"
                        className="cx-link"
                        preserveScroll
                        preserveState
                    >
                        Send the email again
                    </Link>
                    <span role="status">
                        {sent ? 'Sent. Open the link in the email.' : ''}
                    </span>
                </div>
            </div>
        </div>
    );
}
