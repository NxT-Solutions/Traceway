import { Form, Link } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { confirm as confirmIdentity } from '@/routes/profile';

type Props = {
    /** False when the rider opened the account with a connected service and never set a password. */
    hasPassword: boolean;
    /** True when the rider confirmed it is them a moment ago, with a passkey or a connected account. */
    confirmedRecently: boolean;
};

export default function DeleteUser({ hasPassword, confirmedRecently }: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    // Without a password, the rider confirms it is them first, then deletes with no password at all.
    const mustConfirm = !hasPassword && !confirmedRecently;

    return (
        <div className="card">
            <Heading
                variant="small"
                title="Delete account"
                description="Removes your account and every route in it."
            />
            <div className="danger-box">
                <p className="t">This cannot be undone.</p>
                <p className="hint">
                    Course files already on your watch or GPS stay there.
                    Everything in Traceway goes.
                </p>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle className="pr-8">
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted.
                            {hasPassword
                                ? ' Please enter your password to confirm you would like to permanently delete your account.'
                                : ''}
                        </DialogDescription>

                        {mustConfirm ? (
                            <p className="text-sm text-muted-foreground">
                                First confirm it is you, with a passkey or a
                                connected account. Traceway brings you back here
                                to delete the account.
                            </p>
                        ) : null}

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    {hasPassword ? (
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="password"
                                                className="sr-only"
                                            >
                                                Password
                                            </Label>

                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                ref={passwordInput}
                                                placeholder="Password"
                                                autoComplete="current-password"
                                            />

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                    ) : (
                                        <InputError message={errors.password} />
                                    )}

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        {mustConfirm ? (
                                            <Button variant="outline" asChild>
                                                <Link href={confirmIdentity()}>
                                                    Confirm it is you
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                variant="destructive"
                                                disabled={processing}
                                                asChild
                                            >
                                                <button
                                                    type="submit"
                                                    data-test="confirm-delete-user-button"
                                                >
                                                    Delete account
                                                </button>
                                            </Button>
                                        )}
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
