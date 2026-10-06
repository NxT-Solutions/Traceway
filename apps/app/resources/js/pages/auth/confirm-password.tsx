import { Form, Head } from '@inertiajs/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirm password" />

            <PasskeyVerify
                variant="paper"
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirm with passkey"
                loadingLabel="Confirming…"
                separator="or confirm with password"
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="fields"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="field">
                            <div className="lab">
                                <label htmlFor="password">Password</label>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="current-password"
                                autoFocus
                            />
                            <InputError
                                message={errors.password}
                                className="err"
                            />
                        </div>

                        <button
                            type="submit"
                            className="fill big"
                            disabled={processing}
                            data-test="confirm-password-button"
                        >
                            {processing ? 'Confirming…' : 'Confirm password'}
                        </button>
                    </>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirm password',
    description:
        'This is a secure area of the application. Please confirm your password before continuing.',
};
