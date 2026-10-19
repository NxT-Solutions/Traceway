import { Deferred, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Loading,
    NotConnected,
    Unavailable,
} from '@/components/admin/traffic-states';
import TrafficTabs from '@/components/admin/traffic-tabs';

type Part = { connected: boolean; error: string | null };

/**
 * One Traffic page: the tabs, then the part once Cloudflare answered. Each part fails on its own, with a note in
 * place of its numbers, never zeros that look like all clear.
 */
export default function TrafficPage<T extends Part>({
    title,
    insights,
    children,
}: {
    title: string;
    insights?: T;
    children: (insights: T) => ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <TrafficTabs />
            <Deferred data="insights" fallback={<Loading />}>
                {insights === undefined ? (
                    <Loading />
                ) : !insights.connected ? (
                    <NotConnected />
                ) : insights.error !== null ? (
                    <Unavailable error={insights.error} />
                ) : (
                    children(insights)
                )}
            </Deferred>
        </>
    );
}
