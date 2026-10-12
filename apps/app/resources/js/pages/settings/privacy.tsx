import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Account = {
    name: string;
    email: string;
    email_verified_at: string | null;
    two_factor_enabled: boolean;
    created_at: string | null;
};

type ConnectedApp = {
    id: string;
    name: string;
    connected_at: string | null;
};

type StoredRoute = {
    name: string;
    sport: string;
    distance_metres: number;
    created_at: string | null;
};

function written(value: string | null): string {
    if (value === null) {
        return 'Not yet';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

export default function Privacy({
    account,
    routes,
    apps,
}: {
    account: Account;
    routes: StoredRoute[];
    apps: ConnectedApp[];
}) {
    return (
        <>
            <Head title="Your data" />

            <h1 className="sr-only">Your data</h1>

            <div className="card">
                <Heading
                    variant="small"
                    title="Your data"
                    description="What Traceway keeps about you, and a file you can take with you."
                />

                <dl className="grid gap-3 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Name</dt>
                        <dd>{account.name}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Email</dt>
                        <dd>{account.email}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Email confirmed
                        </dt>
                        <dd>{written(account.email_verified_at)}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Two-factor sign-in
                        </dt>
                        <dd>{account.two_factor_enabled ? 'On' : 'Off'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Account opened
                        </dt>
                        <dd>{written(account.created_at)}</dd>
                    </div>
                </dl>

                <div>
                    <h2 className="text-base font-semibold">Saved routes</h2>
                    {routes.length === 0 ? (
                        <p className="desc">No saved routes yet.</p>
                    ) : (
                        <ul className="mt-2 space-y-2">
                            {routes.map((route) => (
                                <li
                                    key={`${route.name}-${route.created_at ?? ''}`}
                                >
                                    {route.name}
                                    <span className="text-muted-foreground">
                                        {' '}
                                        ·{' '}
                                        {(route.distance_metres / 1000).toFixed(
                                            1,
                                        )}{' '}
                                        km · {route.sport}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <p className="desc">
                    The download includes your account and the full geometry of
                    each saved route, with every leg of a multisport plan and
                    the swim you drew. Your password and sign-in secrets stay
                    here. A copy of the file is also sent to {account.email}.
                </p>

                <a className="fill" href="/settings/privacy/export">
                    Download your data
                </a>
            </div>

            <ConnectedApps apps={apps} />
        </>
    );
}

function ConnectedApps({ apps }: { apps: ConnectedApp[] }) {
    const address =
        typeof window === 'undefined'
            ? '/mcp'
            : `${window.location.origin}/mcp`;

    return (
        <div className="card">
            <Heading
                variant="small"
                title="Connected apps"
                description="AI tools you let plan routes for you, like ChatGPT or Claude."
            />

            {apps.length === 0 ? (
                <p className="desc">No apps connected.</p>
            ) : (
                <ul className="overflow-hidden rounded-lg border border-border">
                    {apps.map((app) => (
                        <li
                            key={app.id}
                            className="flex items-center justify-between gap-4 border-b p-4 last:border-b-0"
                        >
                            <div>
                                <p className="font-medium">{app.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    Connected {written(app.connected_at)}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.delete(
                                        `/settings/privacy/apps/${app.id}`,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Disconnect
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <p className="desc">
                To connect one, add <code>{address}</code> as a connector in the
                app, then sign in here when it asks. It can plan routes and save
                them to your library. It never sees your password.
            </p>
        </div>
    );
}
