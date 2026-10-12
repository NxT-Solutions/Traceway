import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { forgetPlannerDrafts } from '@/lib/planner-draft';
import { toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';
import '../../../css/settings-scene.css';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
    {
        title: 'Your data',
        href: '/settings/privacy',
        icon: null,
    },
];

function initials(name: string): string {
    return name
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const cleanup = useMobileNavigation();
    const page = usePage<{ auth: { user: { name: string } } }>();
    const { auth } = page.props;
    const { resolvedAppearance } = useAppearance();
    const name = auth.user.name;

    return (
        <div className="tw-settings" data-theme={resolvedAppearance}>
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
                                page.url.startsWith('/saved')
                                    ? 'page'
                                    : undefined
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
                        <Link
                            className="acct"
                            href={edit()}
                            aria-current="page"
                            aria-label="Account and settings"
                        >
                            <span className="name">{name}</span>
                            <span className="avatar">{initials(name)}</span>
                        </Link>
                    </div>
                </div>
            </header>
            <main className="wrap">
                <h1>Settings</h1>
                <p className="sub">Profile, security and appearance.</p>
                <div className="layout">
                    <nav className="snav" aria-label="Settings sections">
                        {sidebarNavItems.map((item, index) => (
                            <Link
                                key={`${toUrl(item.href)}-${index}`}
                                href={item.href}
                                aria-current={
                                    isCurrentOrParentUrl(item.href)
                                        ? 'page'
                                        : undefined
                                }
                            >
                                {item.title}
                            </Link>
                        ))}
                        <div className="sep" />
                        <Link
                            href={logout()}
                            as="button"
                            className="out"
                            data-test="logout-button"
                            onClick={() => {
                                cleanup();
                                forgetPlannerDrafts();
                                router.flushAll();
                            }}
                        >
                            Log out
                        </Link>
                    </nav>
                    <div className="section">{children}</div>
                </div>
            </main>
        </div>
    );
}
