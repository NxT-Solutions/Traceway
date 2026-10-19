import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

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
};

export default function Login({
    status,
    canResetPassword,
    demoRiders = [],
}: Props) {
    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify variant="paper" />

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
                            </div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
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
                                type="checkbox"
                                id="remember"
                                name="remember"
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

            {status && (
                <div className="text-left text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Try a sport, or sign in with your email.',
};
