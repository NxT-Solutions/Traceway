import { type MouseEvent, type ReactNode, useEffect, useState } from 'react';
import { ConnectionMark } from '@/components/connection-mark';
import { Spinner } from '@/components/ui/spinner';
import type { ConnectionSlug } from '@/types';

type Props = {
    slug: ConnectionSlug;
    href: string;
    className?: string;
    children: ReactNode;
};

/**
 * Sends the rider to a service's own page to log in. A plain link, so the browser follows the redirect off the
 * site. An Inertia visit cannot. While that page loads, the service's mark turns into a spinner.
 */
export default function SignInLink({ slug, href, className, children }: Props) {
    const [opening, setOpening] = useState(false);

    // Back from the service, the browser can show this page from its cache, still busy.
    useEffect(() => {
        const reset = () => setOpening(false);

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

    return (
        <a
            className={className ? `outline ${className}` : 'outline'}
            href={href}
            aria-busy={opening || undefined}
            onClick={open}
        >
            {opening ? (
                <Spinner
                    aria-hidden="true"
                    className="size-5 motion-reduce:animate-none"
                />
            ) : (
                <ConnectionMark slug={slug} size={20} />
            )}
            {children}
        </a>
    );
}
