import { Form, Head, Link } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import { ago, count, when } from '@/components/admin/format';
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
import users, { destroy, index, role, update } from '@/routes/admin/users';
import type { AdminUser, RoleOption } from '@/types/admin';

type Abilities = { manage: boolean; assignRole: boolean; self: boolean };

/**
 * A write that cannot be undone, or that signs someone out, asks first.
 */
function Confirm({
    title,
    description,
    action,
    button,
    danger = false,
    children,
}: {
    title: string;
    description: string;
    action: {
        action: string;
        method: 'delete' | 'post' | 'put' | 'patch' | 'get';
    };
    button: string;
    danger?: boolean;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogTitle className="pr-8">{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Form
                    {...action}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant={danger ? 'destructive' : 'default'}
                                disabled={processing}
                            >
                                {button}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function ShowUser({
    user,
    roles,
    can,
}: {
    user: AdminUser;
    roles: RoleOption[];
    can: Abilities;
}) {
    const roleLabel =
        roles.find((option) => option.value === user.role)?.label ?? user.role;

    return (
        <>
            <Head title={user.name} />

            <Link className="back" href={index()}>
                ← Riders
            </Link>

            <section className="card" aria-labelledby="user-h">
                <header>
                    <h2 id="user-h">{user.name}</h2>
                    <p className="desc">
                        {user.email} ·{' '}
                        <span className="pill on">{roleLabel}</span>
                    </p>
                </header>
                {can.self ? (
                    <p className="note">
                        This is your own account. Change it in <b>Settings</b>,
                        not here.
                    </p>
                ) : null}
                <dl className="facts">
                    <div>
                        <dt>Joined</dt>
                        <dd>{when(user.created_at)}</dd>
                    </div>
                    <div>
                        <dt>Last seen</dt>
                        <dd>{ago(user.last_seen_at)}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>
                            {user.email_verified_at
                                ? `Confirmed ${when(user.email_verified_at)}`
                                : 'Not confirmed'}
                        </dd>
                    </div>
                    <div>
                        <dt>Sign-in</dt>
                        <dd>
                            {user.two_factor
                                ? 'Two-factor on'
                                : 'Password only'}
                            {user.passkeys > 0
                                ? ` · ${count(user.passkeys)} ${user.passkeys === 1 ? 'passkey' : 'passkeys'}`
                                : ''}
                        </dd>
                    </div>
                    <div>
                        <dt>Saved routes</dt>
                        <dd>{count(user.routes)}</dd>
                    </div>
                </dl>
            </section>

            <section className="card" aria-labelledby="linked-h">
                <header>
                    <h2 id="linked-h">Linked</h2>
                </header>
                <ul className="list" aria-label="Watches">
                    {user.watches.length === 0 ? (
                        <li>
                            <small>No watch linked.</small>
                        </li>
                    ) : (
                        user.watches.map((watch, position) => (
                            <li key={`${watch.provider}-${position}`}>
                                <span>
                                    {watch.label ?? 'Watch'}{' '}
                                    <small>({watch.provider})</small>
                                </span>
                                <small>
                                    Last synced {ago(watch.last_used_at)}
                                </small>
                            </li>
                        ))
                    )}
                </ul>
                <ul className="list" aria-label="AI assistants and apps">
                    {user.apps.length === 0 && user.services.length === 0 ? (
                        <li>
                            <small>No assistant or app connected.</small>
                        </li>
                    ) : null}
                    {user.apps.map((app) => (
                        <li key={app.name}>
                            <span>{app.name}</span>
                            <small>Connected {when(app.connected_at)}</small>
                        </li>
                    ))}
                    {user.services.map((service) => (
                        <li key={service.service}>
                            <span>{service.service}</span>
                            <small>Last sent {ago(service.last_sent_at)}</small>
                        </li>
                    ))}
                </ul>
            </section>

            <section className="card" aria-labelledby="sessions-h">
                <header>
                    <h2 id="sessions-h">Signed in</h2>
                    <p className="desc">Browsers with an open session.</p>
                </header>
                <ul className="list">
                    {user.sessions.length === 0 ? (
                        <li>
                            <small>Not signed in anywhere.</small>
                        </li>
                    ) : (
                        user.sessions.map((session, position) => (
                            <li key={`${session.last_active_at}-${position}`}>
                                <span>{session.browser}</span>
                                <small>
                                    Active {ago(session.last_active_at)}
                                </small>
                            </li>
                        ))
                    )}
                </ul>
            </section>

            {can.manage ? (
                <section className="card" aria-labelledby="edit-h">
                    <header>
                        <h2 id="edit-h">Account</h2>
                        <p className="desc">
                            A changed email gets a confirmation link, and the
                            old address is told.
                        </p>
                    </header>
                    <Form
                        {...update.form(user.id)}
                        options={{ preserveScroll: true }}
                        className="section"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="field">
                                    <label htmlFor="name">Name</label>
                                    <div className="inp">
                                        <input
                                            id="name"
                                            name="name"
                                            defaultValue={user.name}
                                            required
                                            autoComplete="off"
                                            aria-invalid={
                                                errors.name ? true : undefined
                                            }
                                            aria-describedby={
                                                errors.name
                                                    ? 'name-error'
                                                    : undefined
                                            }
                                        />
                                    </div>
                                    {errors.name ? (
                                        <p className="err" id="name-error">
                                            {errors.name}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="field">
                                    <label htmlFor="email">Email</label>
                                    <div className="inp">
                                        <input
                                            id="email"
                                            name="email"
                                            type="email"
                                            defaultValue={user.email}
                                            required
                                            autoComplete="off"
                                            aria-invalid={
                                                errors.email ? true : undefined
                                            }
                                            aria-describedby={
                                                errors.email
                                                    ? 'email-error'
                                                    : undefined
                                            }
                                        />
                                    </div>
                                    {errors.email ? (
                                        <p className="err" id="email-error">
                                            {errors.email}
                                        </p>
                                    ) : null}
                                </div>
                                {user.email_verified_at === null ? (
                                    <label className="check">
                                        <input
                                            type="checkbox"
                                            name="confirm_email"
                                            value="1"
                                        />
                                        Mark the email as confirmed
                                    </label>
                                ) : null}
                                <div className="actions">
                                    <button
                                        type="submit"
                                        className="fill"
                                        disabled={processing}
                                    >
                                        Save
                                    </button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>
            ) : null}

            {can.assignRole ? (
                <section className="card" aria-labelledby="role-h">
                    <header>
                        <h2 id="role-h">Role</h2>
                        <p className="desc">
                            An Admin reads the numbers and looks after riders. A
                            Super Admin can also give roles.
                        </p>
                    </header>
                    <Form
                        {...role.form(user.id)}
                        options={{ preserveScroll: true }}
                        className="actions"
                    >
                        {({ processing }) => (
                            <>
                                <label className="vh" htmlFor="role">
                                    Role
                                </label>
                                <span className="filters">
                                    <select
                                        id="role"
                                        name="role"
                                        defaultValue={user.role}
                                    >
                                        {roles.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </span>
                                <button
                                    type="submit"
                                    className="outline"
                                    disabled={processing}
                                >
                                    Give this role
                                </button>
                            </>
                        )}
                    </Form>
                </section>
            ) : null}

            {can.manage ? (
                <section className="card" aria-labelledby="support-h">
                    <header>
                        <h2 id="support-h">Help with sign-in</h2>
                    </header>
                    <div className="actions">
                        <Confirm
                            title="Sign this rider out everywhere?"
                            description="Every browser this rider is signed in on is signed out, and Remember me stops working. Their routes and settings stay."
                            action={users.sessions.destroy.form(user.id)}
                            button="Sign out everywhere"
                        >
                            <button type="button" className="outline">
                                Sign out everywhere
                            </button>
                        </Confirm>
                        {user.two_factor ? (
                            <Confirm
                                title="Turn two-factor off?"
                                description="Only do this once you are sure the request comes from the rider. They sign in with their password alone until they turn it on again."
                                action={users.twoFactor.destroy.form(user.id)}
                                button="Turn two-factor off"
                            >
                                <button type="button" className="outline">
                                    Turn two-factor off
                                </button>
                            </Confirm>
                        ) : null}
                    </div>
                </section>
            ) : null}

            {can.manage ? (
                <section className="card" aria-labelledby="delete-h">
                    <header>
                        <h2 id="delete-h">Delete account</h2>
                    </header>
                    <div className="danger-box">
                        <p className="t">This cannot be undone.</p>
                        <p className="hint">
                            The account, its routes, and its links to watches
                            and apps go. The rider gets an email that it is
                            gone.
                        </p>
                        <div className="actions">
                            <Confirm
                                title={`Delete ${user.name}'s account?`}
                                description="Everything in Traceway for this rider goes, the same as when they delete it themselves."
                                action={destroy.form(user.id)}
                                button="Delete account"
                                danger
                            >
                                <button
                                    type="button"
                                    className="outline danger"
                                >
                                    Delete account
                                </button>
                            </Confirm>
                        </div>
                    </div>
                </section>
            ) : null}
        </>
    );
}
