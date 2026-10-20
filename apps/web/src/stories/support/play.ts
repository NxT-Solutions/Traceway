import { expect, waitFor } from 'storybook/test';

/**
 * Waits until an element shows. A page's text rises in over 0.7 seconds (page.css, errors.css)
 * unless the visitor asked for less motion, and a component's script runs a moment after it
 * renders, so a story looks for the end state rather than the first frame.
 */
export const shows = (element: Element | null): Promise<void> =>
    waitFor(() => expect(element).toBeVisible(), { timeout: 3000 });
