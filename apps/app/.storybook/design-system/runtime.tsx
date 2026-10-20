import '../../resources/css/app.css';
import '../../resources/css/planner.css';
import { composeStories } from '@storybook/react-vite';
import {
    type ComponentType,
    Fragment,
    type ReactNode,
    useSyncExternalStore,
} from 'react';
import { createRoot } from 'react-dom/client';
import { Appearance } from '../../resources/js/stories/support/appearance';

type StoryModule = Parameters<typeof composeStories>[0];

export type Card = { name: string; module: StoryModule };

// The design system's preview frame says Paper or Ink with `data-theme` on <html>. Follow it the way Storybook's
// Appearance toolbar does, so the shadcn screens and the planner switch together.
const theme = {
    get: () =>
        document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
    subscribe: (callback: () => void) => {
        const observer = new MutationObserver(callback);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme'],
        });

        return () => observer.disconnect();
    },
} as const;

function FrameAppearance({ children }: { children: ReactNode }) {
    const mode = useSyncExternalStore(theme.subscribe, theme.get, () => 'light');

    return <Appearance mode={mode}>{children}</Appearance>;
}

/**
 * Builds `window.Traceway` from the stories: each card is the story file's component when it has one, or the stories
 * themselves. `Traceway.preview(element, name)` draws a card's stories, as its preview.html does.
 */
export function mount(cards: Card[]) {
    const namespace: Record<string, unknown> = {};
    const stories: Record<string, [string, ComponentType][]> = {};

    for (const card of cards) {
        const composed = Object.entries(composeStories(card.module)) as [
            string,
            ComponentType & { storyName?: string },
        ][];

        stories[card.name] = composed.map(([exportName, Story]) => [
            Story.storyName ?? exportName,
            Story,
        ]);

        const meta = card.module.default as { component?: ComponentType };
        namespace[card.name] =
            meta?.component ??
            (() => (
                <>
                    {composed.map(([exportName, Story]) => (
                        <Story key={exportName} />
                    ))}
                </>
            ));
    }

    namespace.preview = (element: HTMLElement, name: string) => {
        createRoot(element).render(
            <FrameAppearance>
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 20,
                        padding: 16,
                        fontFamily: 'var(--font-sans)',
                    }}
                >
                    {(stories[name] ?? []).map(([label, Story]) => (
                        <Fragment key={label}>
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 12,
                                    fontWeight: 500,
                                    color: 'var(--muted-foreground)',
                                }}
                            >
                                {label}
                            </p>
                            <div>
                                <Story />
                            </div>
                        </Fragment>
                    ))}
                </div>
            </FrameAppearance>,
        );
    };

    (window as unknown as { Traceway: typeof namespace }).Traceway = namespace;
}
