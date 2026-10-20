import type { Page } from '@inertiajs/core';
import { App, router } from '@inertiajs/react';
import { type ComponentType, useEffect } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { pageLayout } from '@/lib/page-layout';
import type { Auth } from '@/types/auth';
import { rider } from './fixtures';
import { NextPage } from './next-page';

type PageProps = Record<string, unknown>;

/** The resolver of the story on screen. */
let currentResolve: (name: string) => ComponentType = () => NextPage;

const resolveCurrent = (name: string) => currentResolve(name);

/** What the server shares with every page (HandleInertiaRequests), for a signed-in rider. */
export const sharedProps = (
    auth: Auth | { user: null; can: null } = {
        user: rider,
        can: null,
    },
) => ({
    name: 'Traceway',
    auth,
    sidebarOpen: true,
    whatsNew: auth.user ? { unread: 0, items: [] } : null,
    impersonating: null,
    routeServices: [],
    errors: {},
});

/**
 * Renders a page the way Inertia does in the app: inside its layout, with the shared props, the tooltips and the
 * toaster. Forms and links point at the real routes. A story that mocks the server (support/server.ts) answers them,
 * and Inertia swaps in the page that comes back: the same page with errors, another page from `pages`, or a plain
 * stand-in for a page the story does not draw (NextPage). Without a mock, nothing is answered.
 */
export function InertiaPage<P extends PageProps>({
    name,
    component,
    props,
    shared = sharedProps(),
    url = '/',
    pages = {},
}: {
    name: string;
    component: ComponentType<P>;
    props: P;
    shared?: PageProps;
    url?: string;
    /** Other pages a mocked answer can lead to, by their Inertia name. */
    // biome-ignore lint/suspicious/noExplicitAny: each page takes its own props.
    pages?: Record<string, ComponentType<any>>;
}) {
    const initialPage = {
        component: name,
        props: { ...shared, ...props },
        url,
        version: null,
        clearHistory: false,
        encryptHistory: false,
        flash: {},
    } as unknown as Page;

    // Inertia writes the page's own address (/login) into the story's frame. Put the story's back, so the frame
    // still reloads and a story link still opens.
    const storyAddress = window.location.href;

    useEffect(() => {
        const keepStoryAddress = () =>
            window.history.replaceState(window.history.state, '', storyAddress);

        keepStoryAddress();

        // Every visit a mocked answer leads to writes its address too.
        return router.on('navigate', keepStoryAddress);
    }, [storyAddress]);

    // Inertia's router starts once per browser page and keeps the first story's resolver for every later visit, so
    // each story puts its own where that one looks.
    currentResolve = (pageName: string) =>
        (pageName === name
            ? component
            : (pages[pageName] ?? NextPage)) as ComponentType;

    return (
        <TooltipProvider delayDuration={0}>
            <App
                key={`${name}:${JSON.stringify(props)}`}
                initialPage={initialPage}
                initialComponent={component as ComponentType}
                resolveComponent={resolveCurrent}
                defaultLayout={pageLayout}
            />
            <Toaster />
        </TooltipProvider>
    );
}
