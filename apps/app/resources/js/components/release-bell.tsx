import { Link, router, usePage } from '@inertiajs/react';
import type { MouseEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    markAllReleasesRead,
    markReleaseRead,
    releaseAnchor,
    releaseDate,
} from '@/lib/releases';
import { index as whatsNewPage } from '@/routes/releases';
import type { UnreadRelease, WhatsNew } from '@/types/releases';
import '../../css/release-bell.css';

const TOASTED_KEY = 'traceway.release-toasted';

/**
 * Says once, in a toast, that a new version is out. The bell keeps the count
 * after that. The tag is remembered in this browser only. On a phone
 * or tablet the toast would cover the plan drawer, so the bell says it alone.
 */
function useNewReleaseToast(newest: UnreadRelease | undefined): void {
    useEffect(() => {
        if (
            newest === undefined ||
            window.matchMedia('(max-width: 1023px)').matches
        ) {
            return;
        }

        try {
            if (window.localStorage.getItem(TOASTED_KEY) === newest.tag) {
                return;
            }

            window.localStorage.setItem(TOASTED_KEY, newest.tag);
        } catch {
            return;
        }

        toast.info(`Traceway ${newest.tag} is out`, {
            description: newest.headline ?? newest.name,
            action: {
                label: "See what's new",
                onClick: () =>
                    router.visit(
                        `${whatsNewPage.url()}#${releaseAnchor(newest.tag)}`,
                    ),
            },
        });
    }, [newest]);
}

export default function ReleaseBell() {
    const whatsNew = usePage().props.whatsNew as WhatsNew | null | undefined;
    const [open, setOpen] = useState(false);
    const panelId = useId();
    const rootRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);

    useNewReleaseToast(whatsNew?.items[0]);

    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: PointerEvent): void {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        function onKeyDown(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                setOpen(false);
                buttonRef.current?.focus();
            }
        }

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    if (!whatsNew) {
        return null;
    }

    const { unread, items } = whatsNew;

    function readAndOpen(
        event: MouseEvent<HTMLAnchorElement>,
        item: UnreadRelease,
    ): void {
        event.preventDefault();
        const target = event.currentTarget.href;
        setOpen(false);
        markReleaseRead(item.id, () => router.visit(target));
    }

    return (
        <div className="release-bell" ref={rootRef}>
            <button
                ref={buttonRef}
                type="button"
                className="rb-button"
                aria-expanded={open}
                aria-controls={panelId}
                aria-label={
                    unread > 0 ? `What's new, ${unread} unread` : "What's new"
                }
                onClick={() => setOpen((value) => !value)}
            >
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 18 18"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M4.5 7.5a4.5 4.5 0 0 1 9 0v3l1.5 2.5H3l1.5-2.5v-3z"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinejoin="round"
                    />
                    <path
                        d="M7.2 15a1.9 1.9 0 0 0 3.6 0"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                    />
                </svg>
                {unread > 0 ? (
                    <span className="rb-count" aria-hidden="true">
                        {unread > 9 ? '9+' : unread}
                    </span>
                ) : null}
            </button>
            {open ? (
                <section
                    id={panelId}
                    className="rb-panel"
                    aria-labelledby={`${panelId}-title`}
                >
                    <div className="rb-head">
                        <h2 id={`${panelId}-title`}>What's new</h2>
                        {unread > 0 ? (
                            <button
                                type="button"
                                className="rb-all"
                                onClick={() => markAllReleasesRead()}
                            >
                                Mark all as read
                            </button>
                        ) : null}
                    </div>
                    {items.length === 0 ? (
                        <p className="rb-empty">You're up to date.</p>
                    ) : (
                        <ul className="rb-list">
                            {items.map((item) => (
                                <li key={item.id} className="rb-item">
                                    <a
                                        className="rb-link"
                                        href={`${whatsNewPage.url()}#${releaseAnchor(item.tag)}`}
                                        onClick={(event) =>
                                            readAndOpen(event, item)
                                        }
                                    >
                                        <span className="rb-meta">
                                            {item.tag}
                                            {item.published_at
                                                ? ` · ${releaseDate(item.published_at)}`
                                                : ''}
                                        </span>
                                        <span className="rb-name">
                                            {item.name}
                                        </span>
                                        {item.headline ? (
                                            <span className="rb-line">
                                                {item.headline}
                                            </span>
                                        ) : null}
                                    </a>
                                    <button
                                        type="button"
                                        className="rb-check"
                                        aria-label={`Mark ${item.tag} as read`}
                                        title="Mark as read"
                                        onClick={() => markReleaseRead(item.id)}
                                    >
                                        <svg
                                            width="16"
                                            height="16"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M3 8.5l3 3 7-7"
                                                stroke="currentColor"
                                                strokeWidth="1.6"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                            />
                                        </svg>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Link
                        className="rb-foot"
                        href={whatsNewPage.url()}
                        onClick={() => setOpen(false)}
                    >
                        All release notes
                    </Link>
                </section>
            ) : null}
        </div>
    );
}
