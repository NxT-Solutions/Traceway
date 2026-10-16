import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { status } from '@/routes';

export type ServiceNoticeProps = {
    title: string;
    body: string | null;
};

const dismissedKey = 'traceway:service-notice-dismissed';

function dismissedTitle(): string | null {
    try {
        return window.sessionStorage.getItem(dismissedKey);
    } catch {
        return null;
    }
}

/**
 * A line above the map when route drawing, place search, or the maps are down
 * or slow, or when a notice is posted. Closing it hides that one notice for
 * this visit; a new one shows again.
 */
export function ServiceNotice({
    notice,
}: {
    notice: ServiceNoticeProps | null;
}) {
    const [dismissed, setDismissed] = useState(dismissedTitle);

    if (notice === null || dismissed === notice.title) {
        return null;
    }

    const dismiss = () => {
        try {
            window.sessionStorage.setItem(dismissedKey, notice.title);
        } catch {
            // Private windows may refuse storage; the line still closes.
        }

        setDismissed(notice.title);
        // The button goes away with the notice, so focus moves on to the
        // place search instead of back to the top of the page.
        document
            .querySelector<HTMLElement>(
                '.tw-planner main input, .tw-planner main button',
            )
            ?.focus();
    };

    return (
        <div className="svc-note" role="status">
            <p>
                <strong>{notice.title}</strong>
                {notice.body && <span> {notice.body}</span>}{' '}
                <Link href={status()}>See the status page</Link>
            </p>
            <button
                type="button"
                onClick={dismiss}
                aria-label="Close this notice"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 14 14"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M3 3l8 8M11 3l-8 8"
                        stroke="currentColor"
                        strokeWidth="1.6"
                        strokeLinecap="round"
                    />
                </svg>
            </button>
        </div>
    );
}
