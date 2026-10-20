import { type RefObject, useEffect } from 'react';

/**
 * Moves focus to a note once the browser has taken over the page the server drew. A screen reader does not read
 * out a note that is already there when the page loads, and a field that takes focus first would skip it. The
 * server never runs this, so its page stays the same.
 */
export function useFocusOnArrival(
    ref: RefObject<HTMLElement | null>,
    when: boolean,
): void {
    useEffect(() => {
        if (when) {
            ref.current?.focus();
        }
    }, [ref, when]);
}
