/**
 * A service a rider can log in with or connect. Wahoo and Ride with GPS also take routes from Send to device.
 */
export type ConnectionSlug =
    | 'google'
    | 'github'
    | 'discord'
    | 'wahoo'
    | 'ride_with_gps'
    | 'runsignup'
    | 'openstreetmap';

/** How the rider last logged in on this browser: their password, a passkey, or a connected service. */
export type SignInMethod = ConnectionSlug | 'password' | 'passkey';

export type ConnectionGroup = 'sport' | 'accounts' | 'maps';

/** A service on the log in page or the "More ways to log in" page. */
export type SignInOption = {
    slug: ConnectionSlug;
    label: string;
};

/** What the log in and sign up pages offer: up to four services, and how many more wait behind "More". */
export type SignInOptions = {
    featured: SignInOption[];
    more: number;
    lastUsed: SignInMethod | null;
};

export type SignInOptionGroup = {
    key: ConnectionGroup;
    label: string;
    providers: SignInOption[];
};

/** A service waiting to be connected once the rider logs in to the account that has its email. */
export type PendingConnection = {
    slug: ConnectionSlug;
    label: string;
    email: string;
};

export type ConnectionCapability = {
    key:
        | 'sign_in'
        | 'send_routes'
        | 'notifications'
        | 'import_routes'
        | 'sync_routes'
        | 'map_notes';
    label: string;
    /** Not built yet. The rider allows it again when it arrives. */
    later: boolean;
};

/** A service on the connections page, whether or not the rider connected it. */
export type ConnectionDescription = {
    slug: ConnectionSlug;
    label: string;
    group: ConnectionGroup;
    group_label: string;
    purpose: string;
    note: string | null;
    /** What Traceway keeps, in plain sentences. */
    keeps: string[];
    capabilities: ConnectionCapability[];
    /** False when this Traceway no longer has the service set up. A connected one can still be disconnected. */
    available: boolean;
    connected: boolean;
};

export type ConnectedAccount = ConnectionDescription & {
    connected: true;
    /** The email or username at the service, when Traceway may keep it. */
    account_label: string | null;
    needs_reconnect: boolean;
    connected_at: string | null;
    last_signed_in_at: string | null;
    last_sent_at: string | null;
    /** Where the rider removes Traceway at the service itself, when Traceway cannot do it for them. */
    manage_url: string | null;
    /** True when disconnecting also asks the service to withdraw the keys it gave Traceway. */
    withdraws_access: boolean;
    can_disconnect: boolean;
    /** Why the rider cannot disconnect it yet, such as it being their last way to log in. */
    disconnect_blocked: string | null;
};

export type ConnectionsPageProps = {
    connected: ConnectedAccount[];
    available: ConnectionDescription[];
    hasPassword: boolean;
    hasPasskey: boolean;
    /** False until the rider confirms their email. Connect and Disconnect wait for it. */
    emailVerified: boolean;
    /** True right after the rider asked for the confirmation email again. */
    verificationSent: boolean;
};
