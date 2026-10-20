import type { Page } from '@inertiajs/core';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirm as confirmIdentity } from '@/routes/profile';
import { cancel, resend } from '@/routes/profile/email';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
    hasPassword,
    confirmedRecently,
    pendingEmail = null,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    hasPassword: boolean;
    confirmedRecently: boolean;
    /** A new email the rider asked for, waiting for them to open the link sent to it. */
    pendingEmail?: string | null;
}) {
    const { auth } = usePage<PageProps>().props;
    const [email, setEmail] = useState(auth.user.email);
    // Asking for a new email needs the password, or a confirmation a moment ago for an account without one.
    const changingEmail =
        email.trim().toLowerCase() !== auth.user.email.toLowerCase();
    const mustProveIt = changingEmail && !confirmedRecently;
    // Inertia keeps the page's state after a post, so the field takes the email the server kept: a new one waits
    // for its link, and it must not be sent again with the next save.
    const keepServerEmail = (page: Page) =>
        setEmail((page.props as unknown as PageProps).auth.user.email);

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="card">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Your name and the email you log in with. A new email counts once you open the link sent to it."
                />

                {pendingEmail !== null && (
                    <div className="hint" role="status">
                        <p>
                            Waiting for you to confirm{' '}
                            <strong>{pendingEmail}</strong>. Open the link sent
                            there. Until then you log in with {auth.user.email}.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        resend.url(),
                                        {},
                                        {
                                            preserveScroll: true,
                                            onSuccess: keepServerEmail,
                                        },
                                    )
                                }
                            >
                                Send the link again
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.delete(cancel.url(), {
                                        preserveScroll: true,
                                        onSuccess: (page) => {
                                            keepServerEmail(page);
                                            // The note with this button is gone, so focus goes to the email.
                                            document
                                                .getElementById('email')
                                                ?.focus();
                                        },
                                    })
                                }
                            >
                                Keep {auth.user.email}
                            </Button>
                        </div>
                    </div>
                )}

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={keepServerEmail}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustProveIt && hasPassword && (
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        Current password
                                    </Label>

                                    <PasswordInput
                                        id="current_password"
                                        name="current_password"
                                        className="mt-1 block w-full"
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                        aria-describedby="current-password-why"
                                    />

                                    <p
                                        id="current-password-why"
                                        className="text-sm text-muted-foreground"
                                    >
                                        Enter your password to change the email
                                        you log in with.
                                    </p>

                                    <InputError
                                        className="mt-2"
                                        message={errors.current_password}
                                    />
                                </div>
                            )}

                            {/* Shown before the rider types, since the round trip to confirm leaves the page. */}
                            {!confirmedRecently && !hasPassword && (
                                <div className="grid gap-2">
                                    <p className="text-sm text-muted-foreground">
                                        To change the email you log in with,
                                        first{' '}
                                        <Link
                                            href={confirmIdentity()}
                                            className="text-foreground underline underline-offset-4"
                                        >
                                            confirm it is you
                                        </Link>
                                        .
                                    </p>

                                    <InputError
                                        className="mt-2"
                                        message={errors.current_password}
                                    />
                                </div>
                            )}

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser
                hasPassword={hasPassword}
                confirmedRecently={confirmedRecently}
            />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [],
};
