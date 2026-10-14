import { Head, router } from '@inertiajs/react';
import {
    Check,
    Code2,
    Copy,
    ExternalLink,
    type LucideIcon,
    MessageCircle,
    Sparkles,
    SquareTerminal,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';

type ConnectedApp = {
    id: string;
    name: string;
    connected_at: string | null;
};

type Guide = {
    id: string;
    name: string;
    where: string;
    icon: LucideIcon;
    link?: { label: string; href: string };
    command?: string;
    online?: boolean;
    steps: string[];
    note?: string;
};

const asks = [
    'A 40 km gravel loop from Westerlo, past Tongerlo Abbey',
    'A flat 10 km run from Gent-Sint-Pieters station',
    'A brick for Saturday: 30 km ride, then a 5 km run',
    'Show my last five routes',
];

function written(value: string | null): string {
    if (value === null) {
        return 'just now';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

function guides(url: string): Guide[] {
    const cursor = `cursor://anysphere.cursor-deeplink/mcp/install?name=traceway&config=${btoa(JSON.stringify({ url }))}`;
    const vscode = `vscode:mcp/install?${encodeURIComponent(JSON.stringify({ name: 'traceway', type: 'http', url }))}`;

    return [
        {
            id: 'claude',
            name: 'Claude',
            where: 'claude.ai, the desktop app, and your phone',
            icon: MessageCircle,
            online: true,
            link: {
                label: 'Open Claude connectors',
                href: 'https://claude.ai/customize/connectors',
            },
            steps: [
                'Choose Add custom connector.',
                'Name it Traceway and paste the address.',
                'Choose Connect, then sign in to Traceway.',
            ],
            note: 'Signed in to Claude Code with the same account? Traceway is there too.',
        },
        {
            id: 'chatgpt',
            name: 'ChatGPT',
            where: 'chatgpt.com and the ChatGPT apps',
            icon: MessageCircle,
            online: true,
            link: { label: 'Open ChatGPT', href: 'https://chatgpt.com/' },
            steps: [
                'Open Settings, then Apps & Connectors.',
                'Under Advanced settings, turn on Developer mode.',
                'Choose Create, name it Traceway, paste the address, and pick OAuth.',
                'Sign in to Traceway when it asks.',
            ],
            note: 'Custom connectors need a paid ChatGPT plan.',
        },
        {
            id: 'cursor',
            name: 'Cursor',
            where: 'The Cursor editor',
            icon: Code2,
            link: { label: 'Add to Cursor', href: cursor },
            steps: [
                'Cursor opens and offers to install Traceway.',
                'Choose Install, then Connect, and sign in to Traceway.',
            ],
        },
        {
            id: 'vscode',
            name: 'VS Code',
            where: 'Visual Studio Code with Copilot',
            icon: Code2,
            link: { label: 'Add to VS Code', href: vscode },
            steps: [
                'VS Code opens and shows Traceway.',
                'Choose Install, then sign in to Traceway when it asks.',
            ],
        },
        {
            id: 'claude-code',
            name: 'Claude Code',
            where: 'Your terminal',
            icon: SquareTerminal,
            command: `claude mcp add --transport http traceway ${url}`,
            steps: [
                'Run it in your terminal.',
                'In Claude Code, type /mcp, choose traceway, and sign in.',
            ],
        },
        {
            id: 'codex',
            name: 'Codex',
            where: 'The Codex CLI, the IDE extension, and the ChatGPT desktop app',
            icon: SquareTerminal,
            command: `codex mcp add traceway --url ${url}\ncodex mcp login traceway`,
            steps: [
                'Run both lines in your terminal.',
                'The second one opens your browser to sign in to Traceway.',
            ],
        },
    ];
}

/**
 * True when the address only works on this computer, like a DDEV site.
 * Apps that connect from their own servers cannot reach it.
 */
function onThisComputer(url: string): boolean {
    const host = new URL(url).hostname;

    return (
        host === 'localhost' ||
        host === '127.0.0.1' ||
        host === '[::1]' ||
        /\.(ddev\.site|test|localhost)$/.test(host)
    );
}

function useCopy(): [string | null, (key: string, text: string) => void] {
    const [copied, setCopied] = useState<string | null>(null);

    return [
        copied,
        (key, text) => {
            void navigator.clipboard
                .writeText(text)
                .then(() => {
                    setCopied(key);
                    window.setTimeout(
                        () =>
                            setCopied((current) =>
                                current === key ? null : current,
                            ),
                        1800,
                    );
                })
                .catch(() => {});
        },
    ];
}

/**
 * A short chat that shows what a connected assistant does: a question in
 * plain words, and a route on real roads saved to the library.
 */
function ChatScene() {
    return (
        <div className="ai-scene" aria-hidden="true">
            <p className="ai-bubble ai-ask">
                A 40 km gravel loop from Westerlo, past Tongerlo Abbey
            </p>
            <div className="ai-bubble ai-reply">
                <span className="ai-tool">
                    <Sparkles size={14} /> Traceway · plan_loop
                </span>
                <svg
                    viewBox="0 0 220 120"
                    className="ai-map"
                    aria-hidden="true"
                >
                    <path
                        className="ai-roads"
                        d="M0 30 H220 M0 84 H220 M58 0 V120 M150 0 V120 M0 120 L220 0"
                    />
                    <path
                        className="ai-route"
                        d="M58 84 C40 60 52 30 82 24 C110 18 128 30 150 30 C178 30 196 52 186 76 C176 98 150 104 124 98 C100 92 80 104 58 84 Z"
                    />
                    <circle className="ai-start" cx="58" cy="84" r="6" />
                    <circle className="ai-via" cx="186" cy="76" r="5" />
                </svg>
                <span className="ai-saved">
                    <b>41.2 km</b> · 180 m · saved to your library
                </span>
            </div>
        </div>
    );
}

export default function Assistants({
    connector_url: url,
    apps,
}: {
    connector_url: string;
    apps: ConnectedApp[];
}) {
    const [copied, copy] = useCopy();
    const local = onThisComputer(url);

    return (
        <>
            <Head title="AI assistants" />

            <h1 className="sr-only">AI assistants</h1>

            <div className="card ai-hero">
                <div className="ai-intro">
                    <p className="ai-kicker">
                        <Sparkles size={16} aria-hidden="true" /> MCP connector
                    </p>
                    <h2>Plan routes by asking your AI</h2>
                    <p className="desc">
                        Traceway works as an MCP connector, the open standard
                        Claude, ChatGPT, Codex, Cursor, and VS Code use to work
                        with other apps. Connect it once, then ask for a route
                        in your own words. It plans the route on real roads and
                        saves it to your library, ready for your watch.
                    </p>
                </div>
                <ChatScene />
            </div>

            <div className="card">
                <Heading
                    variant="small"
                    title="Your connector address"
                    description="Every app asks for this address. Sign in to Traceway when it asks, and the app can plan for you. It never sees your password."
                />
                <div className="ai-address">
                    <code>{url}</code>
                    <button type="button" onClick={() => copy('address', url)}>
                        {copied === 'address' ? (
                            <Check size={16} aria-hidden="true" />
                        ) : (
                            <Copy size={16} aria-hidden="true" />
                        )}
                        {copied === 'address' ? 'Copied' : 'Copy'}
                    </button>
                </div>
            </div>

            <div className="card">
                <Heading
                    variant="small"
                    title="Add it to your app"
                    description="One click where the app allows it, a few steps where it does not."
                />
                {local ? (
                    <p className="ai-local">
                        <b>This address only works on this computer.</b> Cursor,
                        VS Code, Claude Code, and Codex run here, so they can
                        reach it. Claude and ChatGPT connect from their own
                        servers, so they need a public address. Run{' '}
                        <code>ddev share</code>, open Traceway on the address it
                        gives, and copy the connector address from there.
                    </p>
                ) : null}
                <ul className="ai-apps">
                    {guides(url)
                        .sort(
                            (first, second) =>
                                Number(local && first.online === true) -
                                Number(local && second.online === true),
                        )
                        .map((guide) => {
                            const Icon = guide.icon;

                            return (
                                <li key={guide.id} className="ai-app">
                                    <div className="ai-app-head">
                                        <span className="ai-app-icon">
                                            <Icon
                                                size={20}
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span>
                                            <b>{guide.name}</b>
                                            <small>{guide.where}</small>
                                        </span>
                                        {local && guide.online ? (
                                            <span className="ai-tag">
                                                Needs a public address
                                            </span>
                                        ) : null}
                                    </div>
                                    {guide.command ? (
                                        <div className="ai-command">
                                            <pre>{guide.command}</pre>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    copy(
                                                        guide.id,
                                                        guide.command ?? '',
                                                    )
                                                }
                                            >
                                                {copied === guide.id ? (
                                                    <Check
                                                        size={16}
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <Copy
                                                        size={16}
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {copied === guide.id
                                                    ? 'Copied'
                                                    : 'Copy'}
                                            </button>
                                        </div>
                                    ) : null}
                                    {guide.link ? (
                                        <a
                                            className="ai-add"
                                            href={guide.link.href}
                                            target={
                                                guide.link.href.startsWith(
                                                    'http',
                                                )
                                                    ? '_blank'
                                                    : undefined
                                            }
                                            rel="noopener noreferrer"
                                        >
                                            {guide.link.label}
                                            <ExternalLink
                                                size={16}
                                                aria-hidden="true"
                                            />
                                        </a>
                                    ) : null}
                                    <ol>
                                        {guide.steps.map((step) => (
                                            <li key={step}>{step}</li>
                                        ))}
                                    </ol>
                                    {guide.note ? (
                                        <p className="ai-note">{guide.note}</p>
                                    ) : null}
                                </li>
                            );
                        })}
                </ul>
            </div>

            <div className="card">
                <Heading
                    variant="small"
                    title="Then ask"
                    description="In your own words, like you would ask a friend who knows the roads. Tap one to copy it."
                />
                <ul className="ai-asks">
                    {asks.map((ask) => (
                        <li key={ask}>
                            <button
                                type="button"
                                onClick={() => copy(ask, ask)}
                            >
                                <span>“{ask}”</span>
                                {copied === ask ? (
                                    <Check size={16} aria-label="Copied" />
                                ) : (
                                    <Copy size={16} aria-hidden="true" />
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="card">
                <Heading
                    variant="small"
                    title="Connected"
                    description="Apps that can plan routes and save them to your library. Disconnect one and it loses access straight away."
                />
                {apps.length === 0 ? (
                    <p className="desc">
                        No apps yet. Add one above, and it shows up here.
                    </p>
                ) : (
                    <ul className="ai-connected">
                        {apps.map((app) => (
                            <li key={app.id}>
                                <span className="ai-dot" aria-hidden="true" />
                                <span>
                                    <b>{app.name}</b>
                                    <small>
                                        Connected {written(app.connected_at)}
                                    </small>
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.delete(
                                            `/settings/privacy/apps/${app.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Disconnect
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
