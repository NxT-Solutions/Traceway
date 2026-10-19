/** What a Traffic page shows while Cloudflare is asked, when it is not set up, and when it did not answer. */

export function Loading() {
    return (
        <section className="panel" aria-busy="true">
            <div className="panel-head">
                <h2>Asking Cloudflare</h2>
            </div>
            <div className="skel" />
        </section>
    );
}

export function NotConnected() {
    return (
        <section className="panel">
            <div className="panel-head">
                <h2>Not connected</h2>
            </div>
            <p className="note">
                Traffic comes from Cloudflare. Set{' '}
                <code>CLOUDFLARE_ANALYTICS_TOKEN</code> to a token that can read
                analytics, and <code>CLOUDFLARE_ZONE_ID</code> for requests. For
                visits to the site's pages, also set{' '}
                <code>CLOUDFLARE_ACCOUNT_ID</code> and{' '}
                <code>CLOUDFLARE_WEB_ANALYTICS_SITE_TAG</code>.
            </p>
        </section>
    );
}

export function Unavailable({ error }: { error: string }) {
    return (
        <p className="note" role="status">
            <b>Not available right now.</b> {error}
        </p>
    );
}
