import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ago, count, day } from '@/components/admin/format';
import { create, index, show } from '@/routes/admin/users';
import type { AdminUserPage, RoleOption } from '@/types/admin';

type Filters = {
    search: string;
    role: string | null;
    verified: string;
    two_factor: string;
};

function roleLabel(roles: RoleOption[], value: string): string {
    return roles.find((role) => role.value === value)?.label ?? value;
}

export default function Users({
    users,
    filters,
    roles,
}: {
    users: AdminUserPage;
    filters: Filters;
    roles: RoleOption[];
}) {
    const can = usePage().props.auth.can;
    const query = (page: number) => ({
        query: {
            ...(filters.search ? { search: filters.search } : {}),
            ...(filters.role ? { role: filters.role } : {}),
            ...(filters.verified ? { verified: filters.verified } : {}),
            ...(filters.two_factor ? { two_factor: filters.two_factor } : {}),
            ...(page > 1 ? { page } : {}),
        },
    });

    return (
        <>
            <Head title="Riders" />

            <section className="panel" aria-labelledby="riders-h">
                <div className="panel-head">
                    <div>
                        <h2 id="riders-h">Riders</h2>
                        <p>{count(users.total)} found, newest first.</p>
                    </div>
                    {can?.manageUsers ? (
                        <Link className="fill" href={create()}>
                            Add a rider
                        </Link>
                    ) : null}
                </div>

                <Form
                    {...index.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    transform={(data) =>
                        Object.fromEntries(
                            Object.entries(data).filter(
                                ([, value]) => value !== '',
                            ),
                        )
                    }
                    className="filters"
                    role="search"
                >
                    <div className="inp">
                        <label htmlFor="search" className="vh">
                            Name or email
                        </label>
                        <input
                            id="search"
                            name="search"
                            type="search"
                            placeholder="Name or email"
                            defaultValue={filters.search}
                            autoComplete="off"
                        />
                    </div>
                    <label className="vh" htmlFor="role">
                        Role
                    </label>
                    <select
                        id="role"
                        name="role"
                        defaultValue={filters.role ?? ''}
                        onChange={(event) =>
                            event.currentTarget.form?.requestSubmit()
                        }
                    >
                        <option value="">Every role</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                    <label className="vh" htmlFor="verified">
                        Email
                    </label>
                    <select
                        id="verified"
                        name="verified"
                        defaultValue={filters.verified}
                        onChange={(event) =>
                            event.currentTarget.form?.requestSubmit()
                        }
                    >
                        <option value="">Any email</option>
                        <option value="yes">Confirmed</option>
                        <option value="no">Not confirmed</option>
                    </select>
                    <label className="vh" htmlFor="two_factor">
                        Two-factor
                    </label>
                    <select
                        id="two_factor"
                        name="two_factor"
                        defaultValue={filters.two_factor}
                        onChange={(event) =>
                            event.currentTarget.form?.requestSubmit()
                        }
                    >
                        <option value="">Any sign-in</option>
                        <option value="yes">Two-factor on</option>
                        <option value="no">Two-factor off</option>
                    </select>
                    <button type="submit" className="outline">
                        Search
                    </button>
                </Form>

                {users.users.length === 0 ? (
                    <p className="nothing">No rider matches.</p>
                ) : (
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Rider</th>
                                    <th scope="col">Role</th>
                                    <th scope="col" className="r">
                                        Routes
                                    </th>
                                    <th scope="col" className="hide-sm">
                                        Joined
                                    </th>
                                    <th scope="col" className="hide-sm">
                                        Last seen
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.users.map((user) => (
                                    <tr key={user.id}>
                                        <td>
                                            <span className="who">
                                                <Link href={show(user.id)}>
                                                    {user.name}
                                                </Link>
                                                <small>
                                                    {user.email}
                                                    {user.verified
                                                        ? ''
                                                        : ' · not confirmed'}
                                                    {user.two_factor
                                                        ? ' · two-factor'
                                                        : ''}
                                                </small>
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                className={
                                                    user.role === 'user'
                                                        ? 'pill warn'
                                                        : 'pill on'
                                                }
                                            >
                                                {roleLabel(roles, user.role)}
                                            </span>
                                        </td>
                                        <td className="r">
                                            {count(user.routes)}
                                        </td>
                                        <td className="dim hide-sm">
                                            {user.created_at
                                                ? day(
                                                      user.created_at.slice(
                                                          0,
                                                          10,
                                                      ),
                                                  )
                                                : '—'}
                                        </td>
                                        <td className="dim hide-sm">
                                            {ago(user.last_seen_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {users.pages > 1 ? (
                    <nav className="pager" aria-label="Pages">
                        <span>
                            Page {users.page} of {users.pages}
                        </span>
                        <span className="actions">
                            <Link
                                className="outline"
                                href={index(query(users.page - 1))}
                                aria-disabled={users.page <= 1}
                                preserveScroll
                            >
                                Newer
                            </Link>
                            <Link
                                className="outline"
                                href={index(query(users.page + 1))}
                                aria-disabled={users.page >= users.pages}
                                preserveScroll
                            >
                                Older
                            </Link>
                        </span>
                    </nav>
                ) : null}
            </section>
        </>
    );
}
