import { expect, waitFor } from 'storybook/test';

/**
 * Waits until an element shows. A page's text rises in over 0.7 seconds (page.css, errors.css)
 * unless the visitor asked for less motion, and a component's script runs a moment after it
 * renders, so a story looks for the end state rather than the first frame.
 */
export const shows = (element: Element | null): Promise<void> =>
    waitFor(() => expect(element).toBeVisible(), { timeout: 3000 });

/**
 * Whether the story is one of several on a docs page. There each story gets a small frame of its
 * own, all at once, as wide as the docs column rather than a phone, and only one frame at a time
 * can hold the keyboard. A story that taps through the phone menu or moves the focus shows itself
 * there as it opens, and plays in full on its own page and in the tests.
 */
export const onDocsPage = (): boolean => {
    try {
        return (
            window.parent !== window &&
            new URLSearchParams(window.parent.location.search).get('viewMode') === 'docs'
        );
    } catch {
        // A parent on another address, such as a test runner's, is never a docs page.
        return false;
    }
};
