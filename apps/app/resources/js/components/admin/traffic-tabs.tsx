import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { traffic } from '@/routes/admin';
import {
    backups,
    bots,
    requests,
    security,
    speed,
} from '@/routes/admin/traffic';

const tabs = [
    { label: 'Visitors', href: traffic },
    { label: 'Requests', href: requests },
    { label: 'Security', href: security },
    { label: 'AI and bots', href: bots },
    { label: 'Speed', href: speed },
    { label: 'Backups', href: backups },
];

/** The Traffic pages, one per question Cloudflare can answer. */
export default function TrafficTabs() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav className="tabs" aria-label="Traffic pages">
            {tabs.map((tab) => (
                <Link
                    key={tab.label}
                    href={tab.href()}
                    aria-current={isCurrentUrl(tab.href()) ? 'page' : undefined}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
