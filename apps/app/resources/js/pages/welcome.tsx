import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';

const figures = [
    { label: 'Distance', value: '40 km' },
    { label: 'Ascent', value: '180 m' },
    { label: 'Surface', value: 'Paved' },
] as const;

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Home" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="flex items-center justify-between px-6 py-5">
                    <div className="flex items-center gap-2 text-lg font-semibold">
                        <AppLogoIcon className="size-7 text-primary" />
                        Traceway
                    </div>
                    <nav className="flex items-center gap-2 text-sm">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-md bg-primary px-4 py-2 font-medium text-primary-foreground"
                            >
                                Your routes
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-md px-4 py-2 text-foreground"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-md bg-primary px-4 py-2 font-medium text-primary-foreground"
                                >
                                    Start a route
                                </Link>
                            </>
                        )}
                    </nav>
                </header>
                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-10 px-6 py-16">
                    <div className="max-w-xl space-y-4">
                        <p className="text-sm font-medium text-primary">
                            Routes for the bike computer
                        </p>
                        <h1 className="text-4xl font-semibold tracking-tight md:text-5xl">
                            Plan the ride. Send it to the bike.
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            A paved 40 km from your driveway, then a course file
                            your head unit can follow with no phone.{' '}
                            <a
                                href="/how-it-works"
                                className="font-medium text-foreground underline underline-offset-4"
                            >
                                Read the site
                            </a>
                        </p>
                    </div>
                    <dl className="grid max-w-lg grid-cols-3 gap-3">
                        {figures.map((figure) => (
                            <div
                                key={figure.label}
                                className="rounded-xl border bg-card p-4"
                            >
                                <dt className="text-xs text-muted-foreground">
                                    {figure.label}
                                </dt>
                                <dd className="text-2xl font-semibold tabular-nums">
                                    {figure.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </main>
            </div>
        </>
    );
}
