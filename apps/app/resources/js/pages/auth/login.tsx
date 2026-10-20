import { Form, Head, usePage } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import SignInAlert from '@/components/sign-in-alert';
import LastUsed from '@/components/sign-in-last-used';
import SignInOptionsGrid from '@/components/sign-in-options';
import PendingConnectionNotice from '@/components/sign-in-pending';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { useFocusOnArrival } from '@/hooks/use-focus-on-arrival';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { PendingConnection, SignInOptions } from '@/types';

type DemoRider = {
    key: string;
    name: string;
    description: string;
    role: string;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
    demoRiders?: DemoRider[];
    connections: SignInOptions;
    pendingConnection: PendingConnection | null;
};

export default function Login({
    status,
    canResetPassword,
    demoRiders = [],
    connections,
    pendingConnection,
}: Props) {
    const connectionError = usePage().props.errors.connection;
    const hasServices = connections.featured.length > 0;
    const rememberBox = useRef<HTMLInputElement>(null);
    const statusNote = useRef<HTMLDivElement>(null);
    const [remember, setRemember] = useState(false);

    // A note above the form takes focus instead of the email field, so a screen reader reads it first. One at a
    // time: why a service did not log the rider in, then a service waiting to connect, then any other news. The
    // note that takes focus is not a live note too, or a screen reader reads it twice.
    const notice = connectionError
        ? 'error'
        : pendingConnection
          ? 'pending'
          : status
            ? 'status'
            : null;

    useFocusOnArrival(statusNote, notice === 'status');

    // The server draws the services without "Remember me". A box ticked
    // before the browser took over the page still counts.
    useEffect(() => {
        setRemember(rememberBox.current?.checked ?? false);
    }, []);

    return (
        <>
            <Head title="Log in" />

            {status ? (
                <div
                    className="notice"
                    role={notice === 'status' ? undefined : 'status'}
                    ref={statusNote}
                    tabIndex={-1}
                >
                    <CircleCheck size={20} aria-hidden="true" />
                    <p>{status}</p>
                </div>
            ) : null}

            {pendingConnection ? (
                <PendingConnectionNotice
                    pending={pendingConnection}
                    focus={notice === 'pending'}
                />
            ) : null}

            <SignInAlert message={connectionError} focus={notice === 'error'} />

            <SignInOptionsGrid options={connections} remember={remember} />

            <PasskeyVerify
                variant="paper"
                lastUsed={connections.lastUsed === 'passkey'}
                separator={hasServices ? false : undefined}
            />

            {hasServices ? <p className="divider">or use your email</p> : null}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="fields"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="field">
                            <div className="lab">
                                <label htmlFor="email">Email address</label>
                                {connections.lastUsed === 'password' ? (
                                    <LastUsed id="email-last-used" />
                                ) : null}
                            </div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                aria-describedby={
                                    connections.lastUsed === 'password'
                                        ? 'email-last-used'
                                        : undefined
                                }
                                required
                                autoFocus={notice === null}
                                autoComplete="email"
                                placeholder="email@example.com"
                            />
                            <InputError
                                message={errors.email}
                                className="err"
                            />
                        </div>

                        <div className="field">
                            <div className="lab">
                                <label htmlFor="password">Password</label>
                                {canResetPassword && (
                                    <TextLink href={request()}>
                                        Forgot your password?
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                placeholder="Password"
                            />
                            <InputError
                                message={errors.password}
                                className="err"
                            />
                        </div>

                        <label className="check">
                            <input
                                ref={rememberBox}
                                type="checkbox"
                                id="remember"
                                name="remember"
                                onChange={(event) =>
                                    setRemember(event.currentTarget.checked)
                                }
                            />
                            <span>Remember me</span>
                        </label>

                        <button
                            type="submit"
                            className="fill big"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing ? 'Logging in…' : 'Log in'}
                        </button>

                        <p className="signup">
                            Don't have an account?{' '}
                            <TextLink href={register()}>Sign up</TextLink>
                        </p>
                    </>
                )}
            </Form>

            {demoRiders.length > 0 && (
                <div className="dev" aria-label="Local development only">
                    <p className="kick">Local development only</p>
                    <div className="riders">
                        {demoRiders.map((rider) => (
                            <Form
                                key={rider.key}
                                action={`/login/demo/${rider.key}`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rider"
                                    >
                                        <span className="who">
                                            <b>{rider.name}</b>
                                            <span className="role">
                                                {rider.role}
                                            </span>
                                        </span>
                                        <small>{rider.description}</small>
                                    </button>
                                )}
                            </Form>
                        ))}
                    </div>
                    <p>
                        The password for each example is <b>password</b>.
                    </p>
                </div>
            )}
        </>
    );
}

Login.layout = ({ connections }: Props) => ({
    title: 'Log in to your account',
    description:
        connections.featured.length > 0
            ? 'Use an account you already have, or your email.'
            : 'Try a sport, or sign in with your email.',
});
