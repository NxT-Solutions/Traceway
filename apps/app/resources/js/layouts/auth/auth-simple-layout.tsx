import { Link } from '@inertiajs/react';
import scene from '@/components/login-scene.html?raw';
import '../../../css/login-scene.css';
import { dashboard } from '@/routes';
import type { AuthLayoutProps } from '@/types';

function Mark() {
    return (
        <svg
            width="28"
            height="28"
            viewBox="0 0 28 28"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M6 23 V12 a6 6 0 0 1 6 -6 h4 a6 6 0 0 1 6 6 v3"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="22" cy="22" r="3" fill="currentColor" />
        </svg>
    );
}

export default function AuthSimpleLayout({
    children,
    title = '',
    description = '',
}: AuthLayoutProps) {
    return (
        <div className="tw-login">
            <header className="hdr">
                <div className="wrap">
                    <a className="brand" href="/" aria-label="Traceway home">
                        <Mark />
                        <span>Traceway</span>
                    </a>
                    <nav className="nav" aria-label="Sections">
                        <a href="/#how">How it works</a>
                        <a href="/#sports">Sports</a>
                        <a href="/#devices">Devices</a>
                        <a href="/#faq">FAQ</a>
                    </nav>
                    <div className="hdr-r">
                        <Link className="fill" href={dashboard()}>
                            <span className="long">Open the planner</span>
                            <span className="short">Planner</span>
                        </Link>
                    </div>
                </div>
            </header>
            <main>
                <section className="formcol">
                    <div className="form">
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 10,
                            }}
                        >
                            <h1>{title}</h1>
                            {description ? (
                                <p className="lede">{description}</p>
                            ) : null}
                        </div>
                        {children}
                    </div>
                </section>
                <div dangerouslySetInnerHTML={{ __html: scene }} />
            </main>
        </div>
    );
}
