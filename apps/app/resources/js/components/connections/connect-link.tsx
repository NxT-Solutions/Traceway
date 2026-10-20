import { LoaderCircle } from 'lucide-react';
import { type MouseEvent, useEffect, useState } from 'react';
import { connect } from '@/routes/connections';
import type { ConnectionSlug } from '@/types';

type Props = {
    slug: ConnectionSlug;
    label: string;
    /** True when the rider connected it before and Traceway lost its link. */
    again?: boolean;
    /** Where the rider lands after allowing Traceway at the service. */
    returnTo?: string;
    /** True while the rider cannot connect yet, such as before they confirm their email. */
    disabled?: boolean;
    /** The id of the sentence that says why it is turned off. */
    describedBy?: string;
};

/**
 * Sends the rider to the service's own page to allow Traceway. A plain link, so the browser follows the
 * redirect off the site. An Inertia visit cannot. While that page loads, a spinner sits beside the words, which
 * stay the same. A link cannot be turned off, so while the rider cannot connect it is a button that is off.
 */
export default function ConnectLink({
    slug,
    label,
    again = false,
    returnTo,
    disabled = false,
    describedBy,
}: Props) {
    const [opening, setOpening] = useState(false);

    // Back from the service, the browser can show this page from its cache, still busy.
    useEffect(() => {
        const reset = (event: PageTransitionEvent) => {
            if (event.persisted) {
                setOpening(false);
            }
        };

        window.addEventListener('pageshow', reset);

        return () => window.removeEventListener('pageshow', reset);
    }, []);

    function open(event: MouseEvent<HTMLAnchorElement>): void {
        if (opening) {
            event.preventDefault();

            return;
        }

        // A new tab leaves this page as it is.
        if (
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        setOpening(true);
    }

    const words = again ? `Connect ${label} again` : `Connect ${label}`;

    if (disabled) {
        return (
            <button
                type="button"
                className="outline cx-action"
                disabled
                aria-describedby={describedBy}
            >
                {words}
            </button>
        );
    }

    return (
        <a
            className="outline cx-action"
            href={connect.url(
                slug,
                returnTo === undefined
                    ? undefined
                    : { query: { return: returnTo } },
            )}
            aria-busy={opening || undefined}
            onClick={open}
        >
            {opening ? (
                <LoaderCircle
                    className="cx-spin"
                    size={16}
                    aria-hidden="true"
                />
            ) : null}
            {words}
        </a>
    );
}
