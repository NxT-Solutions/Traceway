import { linkTo } from '@storybook/addon-links';
import type { MouseEvent } from 'react';

/**
 * A click handler that opens another story, the way the app moves on to the next screen, with `linkTo` from the
 * links addon: "New route" opens the empty planner, a library card opens its loop. A link's own address is not
 * followed, since no server is there. A click that is already handled, such as one inside `stayInStory`, is left
 * alone.
 */
export function goToStory(title: string, story: string) {
    return (event: MouseEvent) => {
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        linkTo(title, story)();
    };
}

/**
 * Runs part of a play function with every click kept on this story: links are not followed and `goToStory` does
 * not move on, so the test can check what the click did here, such as a spinner or a spy that was called.
 */
export async function stayInStory<T>(act: () => Promise<T>): Promise<T> {
    const keep = (event: Event) => event.preventDefault();

    window.addEventListener('click', keep, true);

    try {
        return await act();
    } finally {
        window.removeEventListener('click', keep, true);
    }
}
