import { Form, Head, Link } from '@inertiajs/react';
import { index, store } from '@/routes/admin/users';

export default function CreateUser() {
    return (
        <>
            <Head title="Add a rider" />

            <Link className="back" href={index()}>
                ← Riders
            </Link>

            <section className="card" aria-labelledby="create-h">
                <header>
                    <h2 id="create-h">Add a rider</h2>
                    <p className="desc">
                        The email is marked as confirmed. The rider gets a link
                        to choose their own password.
                    </p>
                </header>

                <Form
                    {...store.form()}
                    className="section"
                    disableWhileProcessing
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="field">
                                <label htmlFor="name">Name</label>
                                <div className="inp">
                                    <input
                                        id="name"
                                        name="name"
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
                            <div className="actions">
                                <button
                                    type="submit"
                                    className="fill"
                                    disabled={processing}
                                >
                                    Add and send the link
                                </button>
                                <Link className="textbtn" href={index()}>
                                    Cancel
                                </Link>
                            </div>
                        </>
                    )}
                </Form>
            </section>
        </>
    );
}
