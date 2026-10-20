import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import { expect, fn, waitFor } from 'storybook/test';
import Assistants from '@/pages/settings/assistants';
import { edit } from '@/routes/assistants';
import { disconnect } from '@/routes/privacy/apps';
import { serverTime } from '../../support/flows';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import {
    http,
    inertiaPage,
    network,
    pathOf,
    redirect,
} from '../../support/server';

type Props = ComponentProps<typeof Assistants>;

/**
 * Settings, AI assistants (`/settings/assistants`): the MCP connector address, how to add it to each app, and the
 * apps that can plan routes for the rider now.
 *
 * `CopiesAddress` copies the connector address to the clipboard. `DisconnectsClaude` runs against a mocked server
 * (Mock Service Worker) that answers as PrivacyController@disconnect does: the DELETE is a 303 back to the page,
 * without Claude.
 */
const meta = {
    title: 'Pages/Settings/Assistants',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

function page(props: Props) {
    return () => (
        <InertiaPage
            name="settings/assistants"
            component={Assistants}
            props={props}
            shared={sharedProps()}
            url={edit.url()}
        />
    );
}

const claude: Props['apps'][number] = {
    id: '0199f2a4-6c1b-7d3e-9a8f-2b4c6d8e0f12',
    name: 'Claude',
    connected_at: '2026-10-10T19:00:00Z',
};

const chatGpt: Props['apps'][number] = {
    id: '0199f8c0-1e2d-7a4b-8c6d-3e5f7a9b1c24',
    name: 'ChatGPT',
    connected_at: '2026-10-16T12:30:00Z',
};

const connector_url = 'https://traceway.online/mcp';

export const NothingConnected: Story = {
    render: page({ connector_url, apps: [] }),
};

/** Claude and ChatGPT can plan routes and save them to the library. */
export const Connected: Story = {
    render: page({ connector_url, apps: [claude, chatGpt] }),
};

/** On a development machine, where the apps online cannot reach the address. */
export const OnThisComputer: Story = {
    render: page({
        connector_url: 'https://traceway.ddev.site/mcp',
        apps: [],
    }),
};

/** What went to the clipboard. A test browser has no clipboard to read back, so the story records it. */
const writeText = fn(async (_text: string) => {});

/** Sam copies the connector address, and the button says it is copied. */
export const CopiesAddress: Story = {
    render: page({ connector_url, apps: [] }),
    beforeEach() {
        writeText.mockClear();

        // Stands in front of the browser's clipboard, which stays on Navigator.prototype.
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });

        return () => {
            delete (navigator as { clipboard?: unknown }).clipboard;
        };
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Copy the address', async () => {
            const card = canvas
                .getByText(connector_url)
                .closest('.ai-address') as HTMLElement;

            await userEvent.click(
                card.querySelector('button') as HTMLButtonElement,
            );
        });

        await step('The address is on the clipboard', async () => {
            await waitFor(() =>
                expect(canvas.getAllByText('Copied').length).toBeGreaterThan(0),
            );
            await expect(writeText).toHaveBeenCalledWith(connector_url);
        });
    },
};

/** Which app the mocked server disconnected. */
const disconnectRequest = fn();

/** Sam takes Claude's access back; ChatGPT stays. */
export const DisconnectsClaude: Story = {
    render: Connected.render,
    beforeEach({ msw }) {
        disconnectRequest.mockClear();
        msw.use(
            http.delete(pathOf(disconnect), async ({ params }) => {
                disconnectRequest(params.client);
                await network();

                return redirect(edit.url(), 303);
            }),
            http.get(edit.url(), () =>
                inertiaPage(
                    'settings/assistants',
                    { connector_url, apps: [chatGpt] },
                    { url: edit.url() },
                ),
            ),
        );
    },
    play: async ({ canvas, userEvent, step }) => {
        await step('Disconnect Claude', async () => {
            await userEvent.click(
                canvas.getByRole('button', { name: 'Disconnect Claude' }),
            );
        });

        await step('Only ChatGPT is left', async () => {
            await waitFor(
                () =>
                    expect(
                        canvas.queryByRole('button', {
                            name: 'Disconnect Claude',
                        }),
                    ).toBeNull(),
                serverTime,
            );
            await expect(
                canvas.getByRole('button', { name: 'Disconnect ChatGPT' }),
            ).toBeVisible();
            await expect(disconnectRequest).toHaveBeenCalledWith(claude.id);
        });
    },
};
