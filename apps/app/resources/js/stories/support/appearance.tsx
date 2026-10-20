import { type ReactNode, useEffect, useRef } from 'react';
import { type ResolvedAppearance, useAppearance } from '@/hooks/use-appearance';

/**
 * Sets Paper or Ink the way the app does, so the shadcn screens (the `dark` class) and the planner (`data-theme`
 * from the same hook) switch together with Storybook's Appearance toolbar.
 */
export function Appearance({
    mode,
    children,
}: {
    mode: ResolvedAppearance;
    children: ReactNode;
}) {
    const { updateAppearance } = useAppearance();
    // The hook hands out a new updater on every render, and updating renders again: keep the latest one aside so
    // the effect runs only when the mode changes.
    const update = useRef(updateAppearance);
    update.current = updateAppearance;

    useEffect(() => {
        try {
            update.current(mode);
        } catch {
            // A frame without storage (the design system's previews) still gets the class.
            document.documentElement.classList.toggle('dark', mode === 'dark');
        }
    }, [mode]);

    return children;
}
