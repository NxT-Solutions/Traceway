import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import SceneHeader from '@/components/scene-header';
import { useAppearance } from '@/hooks/use-appearance';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { activity, overview, traffic } from '@/routes/admin';
import { index as users } from '@/routes/admin/users';
import { edit } from '@/routes/profile';
import '../../../css/settings-scene.css';
import '../../../css/admin-scene.css';

export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { resolvedAppearance } = useAppearance();
    const can = usePage().props.auth.can;

    return (
        <div className="tw-settings tw-admin" data-theme={resolvedAppearance}>
            <SceneHeader />
            <main className="wrap">
                <h1>Admin</h1>
                <p className="sub">
                    Who rides with Traceway, and what they use.
                </p>
                <div className="layout">
                    <nav className="snav" aria-label="Admin sections">
                        {can?.viewAnalytics ? (
                            <>
                                <span className="tag" aria-hidden="true">
                                    Numbers
                                </span>
                                <Link
                                    href={overview()}
                                    aria-current={
                                        isCurrentUrl(overview())
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    Overview
                                </Link>
                                <Link
                                    href={traffic()}
                                    aria-current={
                                        isCurrentUrl(traffic())
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    Traffic
                                </Link>
                            </>
                        ) : null}
                        {can?.viewUsers ? (
                            <Link
                                href={users()}
                                aria-current={
                                    isCurrentOrParentUrl(users())
                                        ? 'page'
                                        : undefined
                                }
                            >
                                Riders
                            </Link>
                        ) : null}
                        {can?.viewUsers ? (
                            <Link
                                href={activity()}
                                aria-current={
                                    isCurrentUrl(activity())
                                        ? 'page'
                                        : undefined
                                }
                            >
                                Activity
                            </Link>
                        ) : null}
                        <div className="sep" />
                        <Link href={edit()}>Back to Settings</Link>
                    </nav>
                    <div className="section">{children}</div>
                </div>
            </main>
        </div>
    );
}
