/**
 * The planner keeps its unsaved draft in this browser, start point
 * included. Each rider gets their own key, so the next person to sign in
 * on a shared browser does not open someone else's draft.
 */
const prefix = 'traceway.planner.';

export function plannerDraftKey(userId: number): string {
    return `${prefix}v1.${userId}`;
}

/**
 * Before drafts had a rider's id in the key, every rider on a browser
 * shared this one. It may belong to someone else, so it is dropped.
 */
export function forgetSharedPlannerDraft(): void {
    try {
        window.localStorage.removeItem(`${prefix}v1`);
    } catch {
        // Storage can be blocked. Then there is no draft to forget.
    }
}

/**
 * Removes every planner draft from this browser, including the old
 * unscoped one. Called on log out.
 */
export function forgetPlannerDrafts(): void {
    try {
        const keys = Object.keys(window.localStorage).filter((key) =>
            key.startsWith(prefix),
        );

        for (const key of keys) {
            window.localStorage.removeItem(key);
        }
    } catch {
        // Storage can be blocked. Then there is no draft to forget.
    }
}
