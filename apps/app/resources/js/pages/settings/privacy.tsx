import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { edit as editConnections } from '@/routes/connections';

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

                <div>
                    <h2 className="text-base font-semibold">
                        Connected accounts
                    </h2>
                    <p className="desc">
                        The accounts you log in with or send routes to are under{' '}
                        <Link className="cx-link" href={editConnections()}>
                            Connections
                        </Link>
                        , each with what Traceway keeps from it.
                    </p>
                </div>

                <p className="desc">
                    The download includes your account, the accounts you
                    connected, and the full geometry of each saved route, with
                    every leg of a multisport plan and the swim you drew. Your
                    password and sign-in secrets stay here. A copy of the file
                    is also sent to {account.email}.
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
    return (
        <div className="card">
            <Heading
                variant="small"
                title="AI assistants"
                description={
                    apps.length === 0
                        ? 'No AI assistant can plan routes for you yet.'
                        : `${apps.length === 1 ? 'One app' : `${apps.length} apps`} can plan routes and save them to your library: ${apps.map((app) => app.name).join(', ')}.`
                }
            />
            <div className="ai-pointer">
                <Link className="outline" href="/settings/assistants">
                    {apps.length === 0
                        ? 'Connect Claude, ChatGPT, and more'
                        : 'Manage AI assistants'}
                </Link>
            </div>
        </div>
    );
}
