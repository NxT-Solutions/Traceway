import type { StoryContext } from '@storybook/react-vite';
import { expect, waitFor } from 'storybook/test';

/** The part of a play function's context the flow helpers use. */
export type Play = Pick<StoryContext, 'canvas' | 'userEvent'>;

/**
 * How long a play function waits for the page to change after a mocked answer: the 400 ms of `network()` and the
 * page update, with room for a busy test machine. `waitFor(check, serverTime)`.
 */
export const serverTime = { timeout: 3000 };

/** Checks the flow reached a page the story draws with the NextPage stand-in. */
export async function expectNextPage(canvas: Play['canvas'], name: string) {
    await expect(
        await canvas.findByRole('heading', { name: `Next page: ${name}` }),
    ).toBeVisible();
}

/** Checks the toast a flashed `toast` shows (Inertia::flash('toast', …) in the controller). */
export async function expectToast(canvas: Play['canvas'], message: string) {
    const toast = await canvas.findByText(message);

    // Sonner fades a toast in.
    await waitFor(() => expect(toast).toBeVisible());
}
