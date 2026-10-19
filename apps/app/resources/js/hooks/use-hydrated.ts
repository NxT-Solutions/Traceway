import { useSyncExternalStore } from 'react';

const noSubscription = () => () => {};

/**
 * False on the server and while the browser takes over the page the server
 * drew, true afterwards. A page renders browser-only things, such as the
 * reader's time zone or the time now, once this is true, so the first render
 * matches the server's.
 */
export function useHydrated(): boolean {
    return useSyncExternalStore(
        noSubscription,
        () => true,
        () => false,
    );
}
