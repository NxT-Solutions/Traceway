import { Link, usePage } from '@inertiajs/react';
import ReleaseBell from '@/components/release-bell';
import { edit } from '@/routes/profile';

function initials(name: string): string {
    return name
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

/**
 * The header of the quiet pages, Settings and the admin area: the mark, Plan, Saved routes, the bell, and the account.
 */
export default function SceneHeader({ current }: { current?: 'account' }) {
    const page = usePage<{ auth: { user: { name: string } } }>();
    const name = page.props.auth.user.name;

    return (
        <header className="hdr">
            <div className="wrap">
                <Link
                    className="brand"
                    href="/dashboard"
                    aria-label="Traceway planner"
                >
                    <svg
                        width="26"
                        height="26"
                        viewBox="0 0 28 28"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M6 23 V12 a6 6 0 0 1 6 -6 h4 a6 6 0 0 1 6 6 v3"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                        <circle
                            cx="22"
                            cy="22"
                            r="3"
                            fill="oklch(0.78 0.17 128)"
                        />
                    </svg>
                    <span>Traceway</span>
                </Link>
                <div className="hdr-r">
                    <Link className="plan-nav" href="/dashboard">
                        <span className="long">Plan your route</span>
                        <span className="short">Plan</span>
                    </Link>
                    <Link
                        className="saved-nav"
                        href="/saved"
                        aria-label="Saved routes"
                        aria-current={
                            page.url.startsWith('/saved') ? 'page' : undefined
                        }
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 2.5h8a1 1 0 0 1 1 1V14l-5-2.5L3 14V3.5a1 1 0 0 1 1-1z"
                                stroke="currentColor"
                                strokeWidth="1.4"
                                strokeLinejoin="round"
                            />
                        </svg>
                        <span>Saved routes</span>
                    </Link>
                    <ReleaseBell />
                    <Link
                        className="acct"
                        href={edit()}
                        aria-current={
                            current === 'account' ? 'page' : undefined
                        }
                        aria-label="Account and settings"
                    >
                        <span className="name">{name}</span>
                        <span className="avatar">{initials(name)}</span>
                    </Link>
                </div>
            </div>
        </header>
    );
}
