import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { destroy } from '@/routes/admin/impersonation';

type Impersonating = { name: string } | null | undefined;

function impersonating(page: Page | undefined): Impersonating {
    return (page?.props as { impersonating?: Impersonating } | undefined)
        ?.impersonating;
}

/**
 * Shows on every page while a Super Admin sees Traceway as a rider, with the way back: at the top on a wide screen,
 * at the bottom on a phone, where the header has no room. It sits outside the pages, so it follows the page from
 * Inertia's navigation instead of usePage.
 */
export default function ImpersonationBar({ initial }: { initial?: Page }) {
    const [who, setWho] = useState<Impersonating>(impersonating(initial));

    useEffect(
        () =>
            router.on('navigate', (event) => {
                setWho(impersonating(event.detail.page));
            }),
        [],
    );

    if (!who) {
        return null;
    }

    return (
        <div
            role="status"
            className="fixed bottom-[max(12px,env(safe-area-inset-bottom))] left-1/2 sm:top-[max(8px,env(safe-area-inset-top))] sm:bottom-auto z-[1000] flex max-w-[calc(100%-16px)] -translate-x-1/2 items-center gap-3 rounded-full border border-[oklch(0.78_0.17_128)] bg-[oklch(0.22_0.03_200)] py-1.5 pr-1.5 pl-4 text-sm text-[oklch(0.97_0.012_95)] shadow-lg"
        >
            <span className="truncate">
                Acting as <b className="font-semibold">{who.name}</b>
            </span>
            <button
                type="button"
                className="h-8 shrink-0 cursor-pointer rounded-full bg-[oklch(0.78_0.17_128)] px-3 font-semibold text-[oklch(0.2_0.04_200)] hover:bg-[oklch(0.71_0.17_128)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[oklch(0.97_0.012_95)]"
                onClick={() => router.visit(destroy())}
            >
                Stop
                <span className="sr-only sm:not-sr-only">
                    {' '}
                    acting as {who.name}
                </span>
            </button>
        </div>
    );
}
