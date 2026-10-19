import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { forgetPlannerDrafts } from '@/lib/planner-draft';
import { toUrl } from '@/lib/utils';
import { logout, status } from '@/routes';
import { overview as adminOverview } from '@/routes/admin';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { index as whatsNewPage } from '@/routes/releases';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';
import '../../../css/settings-scene.css';
import SceneHeader from '@/components/scene-header';

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
        title: 'Devices',
        href: '/settings/devices',
        icon: null,
    },
    {
        title: 'AI assistants',
        href: '/settings/assistants',
        icon: null,
    },
    {
        title: 'Your data',
        href: '/settings/privacy',
        icon: null,
    },
    {
        title: "What's new",
        href: whatsNewPage(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const cleanup = useMobileNavigation();
    const page = usePage();
    const canAdmin = page.props.auth.can?.admin === true;
    const { resolvedAppearance } = useAppearance();
    const unread = page.props.whatsNew?.unread ?? 0;

    return (
        <div className="tw-settings" data-theme={resolvedAppearance}>
            <SceneHeader current="account" />
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
                                {toUrl(item.href) === whatsNewPage.url() &&
                                unread > 0 ? (
                                    <span className="count">
                                        {unread}
                                        <span className="sr-only"> unread</span>
                                    </span>
                                ) : null}
                            </Link>
                        ))}
                        {canAdmin ? (
                            <Link href={adminOverview()}>Admin</Link>
                        ) : null}
                        <div className="sep" />
                        <a href="/support">Support Traceway</a>
                        <Link href={status()}>Service status</Link>
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
