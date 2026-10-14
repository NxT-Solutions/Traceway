import { router } from '@inertiajs/react';
import ReleasesController from '@/actions/App/Http/Controllers/Settings/ReleasesController';

/** The props a read changes: the bell everywhere, and the list on What's new. */
const reloaded = ['whatsNew', 'releases'];

/** The id of a release on the What's new page, such as v1-4-0 for v1.4.0. */
export function releaseAnchor(tag: string): string {
    return (
        tag
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '') || 'release'
    );
}

/** A release date in words, such as 14 October 2026. */
export function releaseDate(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(
        new Date(value),
    );
}

export function markReleaseRead(id: number, onFinish?: () => void): void {
    router.post(
        ReleasesController.read.url(id),
        {},
        { preserveScroll: true, preserveState: true, only: reloaded, onFinish },
    );
}

export function markAllReleasesRead(onFinish?: () => void): void {
    router.post(
        ReleasesController.readAll.url(),
        {},
        { preserveScroll: true, preserveState: true, only: reloaded, onFinish },
    );
}

/**
 * Moves focus to an element after the button that had it is gone, so a
 * keyboard does not fall back to the top of the page.
 */
export function focusAfterRead(id: string): void {
    requestAnimationFrame(() => document.getElementById(id)?.focus());
}
