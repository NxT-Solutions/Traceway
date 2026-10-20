import { router } from '@inertiajs/react';
import { linkTo } from '@storybook/addon-links';
import type { Decorator } from '@storybook/react-vite';
import { type ReactNode, useEffect } from 'react';

/** A page address, and the story that draws it: `[title, story]`, as in the sidebar. */
export type StoryLinks = Record<string, [title: string, story?: string]>;

function FollowLinks({
    links,
    children,
}: {
    links: StoryLinks;
    children: ReactNode;
}) {
    useEffect(
        () =>
            router.on('before', (event) => {
                const { visit } = event.detail;
                const story = links[visit.url.pathname];

                if (visit.method !== 'get' || !story) {
                    return;
                }

                // Open that page's own story instead of asking a server that is not there.
                linkTo(...story)();

                return false;
            }),
        [links],
    );

    return children;
}

/**
 * Follows a page's links to the stories of the pages they lead to, with `linkTo` from the links addon: "Sign up" on
 * Log in opens the Register story. Only plain GET visits to the addresses listed here change story, so a form the
 * story's server mock answers still works as in the app. A story that mocks one of these links itself, to show the
 * visit, turns this off with `parameters: { followLinks: false }`.
 */
export function followLinks(links: StoryLinks): Decorator {
    return (Story, { parameters }) =>
        parameters.followLinks === false ? (
            <Story />
        ) : (
            <FollowLinks links={links}>
                <Story />
            </FollowLinks>
        );
}
